<?php

namespace App\Services\Crm;

use App\Models\Crm\Client;
use App\Models\Crm\CrmEmailMessage;
use App\Models\Crm\CrmEmailThread;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Single place that turns a received email (webhook payload or an IMAP
 * message) into CRM thread/message rows. Both channels share this so the
 * Email page shows one consistent inbox.
 */
class InboundEmailImporter
{
    /**
     * @param  array{from:string,from_name?:?string,subject?:?string,body:string,message_id?:?string,received_at?:CarbonInterface|string|null}  $data
     * @return array{thread:CrmEmailThread,message:CrmEmailMessage}|null  null when invalid or already imported.
     */
    public function import(array $data): ?array
    {
        $fromEmail = strtolower(trim((string) ($data['from'] ?? '')));
        $fromName = $data['from_name'] ?? null;
        $subject = trim((string) ($data['subject'] ?? ''));
        $body = (string) ($data['body'] ?? '');
        $messageId = $data['message_id'] ?? null;
        $receivedAt = $data['received_at'] ?? null;

        // Accept "Name <email@example.com>" as well as a bare address.
        if (preg_match('/^\s*(.*?)\s*<([^>]+)>\s*$/', $fromEmail, $matches)) {
            $fromName = $fromName ?: trim($matches[1], " \t\"'");
            $fromEmail = strtolower(trim($matches[2]));
        }

        if (! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        // Deduplicate: the same Message-ID must never create a second row.
        if (is_string($messageId) && $messageId !== ''
            && CrmEmailMessage::where('message_id', $messageId)->exists()) {
            return null;
        }

        $subject = $subject !== '' ? $subject : '(No subject)';
        $when = $receivedAt instanceof CarbonInterface
            ? $receivedAt
            : ($receivedAt ? Carbon::parse($receivedAt) : now());

        $client = Client::whereRaw('LOWER(email) = ?', [$fromEmail])->first();

        $thread = CrmEmailThread::firstOrCreate(
            ['from_email' => $fromEmail],
            [
                'client_id' => $client?->id,
                'from_name' => $fromName ?: $client?->contact_person,
                'subject' => $subject,
                'last_message_at' => $when,
            ],
        );

        $thread->update([
            'client_id' => $thread->client_id ?: $client?->id,
            'from_name' => $thread->from_name ?: $fromName,
            'subject' => $thread->subject ?: $subject,
            'last_message_preview' => Str::limit(trim(strip_tags($body)), 160),
            'last_message_at' => $when,
        ]);

        $message = CrmEmailMessage::create([
            'thread_id' => $thread->id,
            'direction' => 'inbound',
            'type' => 'inbound',
            'from_email' => $fromEmail,
            'from_name' => $fromName ?: $thread->from_name,
            'to_email' => config('mail.from.address'),
            'to_name' => config('mail.from.name'),
            'subject' => $subject,
            'body' => $body,
            'message_id' => ($messageId ?: null) ?: (string) Str::uuid(),
            'status' => 'received',
            'sent_at' => $when,
            'created_at' => $when,
            'updated_at' => $when,
        ]);

        return ['thread' => $thread, 'message' => $message];
    }
}
