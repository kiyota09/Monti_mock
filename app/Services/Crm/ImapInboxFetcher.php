<?php

namespace App\Services\Crm;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Polls an IMAP mailbox (default: the Gmail account that also sends CRM mail)
 * and imports unseen messages into the CRM Email inbox.
 *
 * Requires the PHP `imap` extension. Failures never throw — a mailbox that is
 * unreachable or misconfigured simply yields an error string so the Email page
 * keeps working.
 */
class ImapInboxFetcher
{
    public function __construct(protected InboundEmailImporter $importer)
    {
    }

    /**
     * @return array{imported:int,skipped:int,error:?string}
     */
    public function fetch(): array
    {
        $cfg = config('crm_email.imap');

        if (! ($cfg['enabled'] ?? false)) {
            return $this->result(error: 'IMAP polling is disabled (CRM_EMAIL_IMAP_ENABLED=false).');
        }

        if (! function_exists('imap_open')) {
            return $this->result(error: 'The PHP imap extension is not enabled.');
        }

        if (empty($cfg['username']) || empty($cfg['password'])) {
            return $this->result(error: 'No IMAP credentials configured (set MAIL_USERNAME / MAIL_PASSWORD or CRM_EMAIL_IMAP_*).');
        }

        $connection = $this->connect($cfg);
        if (is_string($connection)) {
            return $this->result(error: $connection);
        }

        $imported = 0;
        $skipped = 0;
        $markSeen = (bool) ($cfg['mark_seen'] ?? true);

        try {
            $search = strtoupper((string) ($cfg['search'] ?? 'UNSEEN')) === 'ALL' ? 'ALL' : 'UNSEEN';
            $ids = @imap_search($connection, $search) ?: [];

            // Newest first, capped so one huge mailbox never blocks the page.
            sort($ids);
            $ids = array_slice($ids, -1 * max(1, (int) ($cfg['limit'] ?? 30)));

            foreach ($ids as $msgno) {
                try {
                    $parsed = $this->parse($connection, (int) $msgno);
                    if ($parsed === null) {
                        $skipped++;
                        continue;
                    }

                    $stored = $this->importer->import($parsed);
                    $stored ? $imported++ : $skipped++;

                    // Clear from the unseen set either way so it is not rescanned.
                    if ($markSeen) {
                        @imap_setflag_full($connection, (string) $msgno, '\\Seen');
                    }
                } catch (\Throwable $e) {
                    $skipped++;
                    Log::warning('CRM IMAP: failed to parse message #' . $msgno . ': ' . $e->getMessage());
                }
            }
        } finally {
            @imap_close($connection);
        }

        return $this->result($imported, $skipped);
    }

    /**
     * @param  array<string,mixed>  $cfg
     * @return \IMAP\Connection|string  connection or error string
     */
    protected function connect(array $cfg)
    {
        $flags = '/' . ($cfg['encryption'] ?: 'ssl');
        if (! ($cfg['verify_peer'] ?? false)) {
            $flags .= '/novalidate-cert';
        }

        $mailbox = '{' . $cfg['host'] . ':' . $cfg['port'] . $flags . '}' . ($cfg['folder'] ?: 'INBOX');

        // Gmail offers GSSAPI in its capabilities; PHP's default authenticator
        // can trip on it, so disable it explicitly.
        $connection = @imap_open(
            $mailbox,
            (string) $cfg['username'],
            (string) $cfg['password'],
            0,
            1,
            ['DISABLE_AUTHENTICATOR' => 'GSSAPI'],
        );

        if ($connection === false) {
            $error = imap_last_error() ?: 'Unable to open the mailbox.';
            Log::warning('CRM IMAP: ' . $error);

            return $error;
        }

        return $connection;
    }

    /**
     * @return array{from:string,from_name:?string,subject:?string,body:string,message_id:?string,received_at:?Carbon}|null
     */
    protected function parse($connection, int $msgno): ?array
    {
        $header = @imap_headerinfo($connection, $msgno);
        if ($header === false) {
            return null;
        }

        $from = $header->from[0] ?? null;
        if ($from === null) {
            return null;
        }

        $fromEmail = strtolower(($from->mailbox ?? '') . '@' . ($from->host ?? ''));
        if (! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $fromName = isset($from->personal) ? $this->decodeMime($from->personal) : null;
        $subject = isset($header->subject) ? $this->decodeMime($header->subject) : null;

        $rawHeader = @imap_fetchheader($connection, $msgno) ?: '';
        $messageId = null;
        if (preg_match('/^message-id:\s*(.+)$/im', $rawHeader, $m)) {
            $messageId = trim($m[1]);
        }

        $receivedAt = null;
        if (! empty($header->date)) {
            try {
                $receivedAt = Carbon::parse($header->date);
            } catch (\Throwable) {
                $receivedAt = null;
            }
        }

        $body = $this->body($connection, $msgno);

        return [
            'from' => $fromEmail,
            'from_name' => $fromName ?: null,
            'subject' => $subject ?: null,
            'body' => $body !== '' ? $body : '(This message has no readable text body.)',
            'message_id' => $messageId,
            'received_at' => $receivedAt,
        ];
    }

    protected function body($connection, int $msgno): string
    {
        $structure = @imap_fetchstructure($connection, $msgno);
        if ($structure === false) {
            return (string) @imap_body($connection, $msgno);
        }

        if (empty($structure->parts)) {
            $text = $this->decodePart((string) @imap_body($connection, $msgno), (int) ($structure->encoding ?? 0));

            return $this->toUtf8($text, $this->charsetOf($structure));
        }

        $plain = $this->findPart($connection, $msgno, $structure->parts, '', 'PLAIN');
        if ($plain !== null) {
            return $plain;
        }

        $html = $this->findPart($connection, $msgno, $structure->parts, '', 'HTML');
        if ($html !== null) {
            return $this->htmlToText($html);
        }

        return $this->toUtf8($this->decodePart((string) @imap_body($connection, $msgno), 0), null);
    }

    /**
     * Walk the MIME tree looking for the first part of the requested subtype.
     *
     * @param  array<int,object>  $parts
     */
    protected function findPart($connection, int $msgno, array $parts, string $prefix, string $subtype): ?string
    {
        foreach ($parts as $index => $part) {
            $section = $prefix === '' ? (string) ($index + 1) : $prefix . '.' . ($index + 1);

            if (! empty($part->parts)) {
                $found = $this->findPart($connection, $msgno, $part->parts, $section, $subtype);
                if ($found !== null) {
                    return $found;
                }
                continue;
            }

            $isText = ((int) ($part->type ?? -1)) === 0;
            if ($isText && strtoupper((string) ($part->subtype ?? '')) === $subtype) {
                $raw = (string) @imap_fetchbody($connection, $msgno, $section);
                $decoded = $this->decodePart($raw, (int) ($part->encoding ?? 0));

                return $this->toUtf8($decoded, $this->charsetOf($part));
            }
        }

        return null;
    }

    protected function decodePart(string $data, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($data, false) ?: $data,
            4 => quoted_printable_decode($data),
            default => $data,
        };
    }

    protected function charsetOf(?object $part): ?string
    {
        foreach (($part->parameters ?? []) as $parameter) {
            if (strtoupper((string) ($parameter->attribute ?? '')) === 'CHARSET') {
                return (string) $parameter->value ?: null;
            }
        }

        return null;
    }

    protected function toUtf8(string $text, ?string $charset): string
    {
        $charset = $charset ? strtoupper($charset) : null;

        if ($charset && ! in_array($charset, ['UTF-8', 'US-ASCII', 'ASCII'], true)) {
            try {
                $text = mb_convert_encoding($text, 'UTF-8', $charset);
            } catch (\Throwable) {
                // Leave the original text when the charset is unknown.
            }
        }

        return $text;
    }

    protected function decodeMime(string $value): string
    {
        return function_exists('imap_utf8') ? @imap_utf8($value) : $value;
    }

    protected function htmlToText(string $html): string
    {
        $text = preg_replace('/<(br|\/p|\/div|\/tr)[^>]*>/i', "\n", $html) ?? $html;
        $text = strip_tags($text);

        return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * @return array{imported:int,skipped:int,error:?string}
     */
    protected function result(int $imported = 0, int $skipped = 0, ?string $error = null): array
    {
        return ['imported' => $imported, 'skipped' => $skipped, 'error' => $error];
    }
}
