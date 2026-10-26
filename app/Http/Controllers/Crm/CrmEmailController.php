<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Mail\CrmReplyMail;
use App\Mail\RegistrationInviteMail;
use App\Models\Crm\Client;
use App\Models\Crm\CrmEmailMessage;
use App\Models\Crm\CrmEmailThread;
use App\Models\Crm\CrmRegistrationInvite;
use App\Services\Crm\ImapInboxFetcher;
use App\Services\Crm\InboundEmailImporter;
use App\Support\RouteHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * CRM email inbox.
 *
 * Received client email is pulled from the configured IMAP mailbox on page
 * load (throttled) and by the `crm:sync-email` scheduler command; an inbound
 * webhook is also supported for providers that push instead. Outbound replies
 * and registration-form invitations are sent through Laravel Mail and
 * mirrored into the thread so the whole conversation stays visible.
 */
class CrmEmailController extends Controller
{
    public function index(Request $request)
    {
        $this->syncInboxIfDue();

        $threads = CrmEmailThread::query()
            ->with([
                'client:id,company_name,contact_person,email',
                'contact:id,name,email',
                'latestMessage',
            ])
            ->withCount([
                'messages as unread_count' => fn ($q) => $q
                    ->where('direction', 'inbound')
                    ->whereNull('read_at'),
            ])
            ->withCount('invites')
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->orderByDesc('id')
            ->get();

        $selected = null;
        $key = $request->query('thread');
        if (is_string($key) && $key !== '') {
            $id = RouteHash::decode($key, 'crm-email-thread');
            $selected = $id ? $threads->firstWhere('id', $id) : null;
        }
        $selected ??= $threads->first();

        if ($selected && (int) $selected->unread_count > 0) {
            CrmEmailMessage::query()
                ->where('thread_id', $selected->id)
                ->where('direction', 'inbound')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
            $selected->unread_count = 0;
        }

        $mapThread = function (CrmEmailThread $thread) {
            $last = $thread->latestMessage;

            return [
                'id' => $thread->id,
                'hash_key' => $thread->hash_key,
                'client_id' => $thread->client_id,
                'company_name' => $thread->client?->company_name,
                'contact_name' => $thread->contact?->name ?? $thread->from_name,
                'from_name' => $thread->from_name,
                'from_email' => $thread->from_email,
                'subject' => $thread->subject,
                'last_message' => $thread->last_message_preview,
                'last_sender' => $last?->direction,
                'last_status' => $last?->status,
                'last_message_at' => $thread->last_message_at?->toIso8601String(),
                'unread_count' => (int) $thread->unread_count,
                'invites_count' => (int) ($thread->invites_count ?? 0),
                'registration_sent_at' => $thread->registration_sent_at?->toIso8601String(),
                'created_at' => $thread->created_at?->toIso8601String(),
            ];
        };

        $threadList = $threads->map($mapThread)->values();

        $messages = $selected
            ? $selected->messages()
                ->with('user:id,name')
                ->get()
                ->map(fn (CrmEmailMessage $m) => [
                    'id' => $m->id,
                    'direction' => $m->direction,
                    'type' => $m->type,
                    'from_email' => $m->from_email,
                    'from_name' => $m->from_name,
                    'to_email' => $m->to_email,
                    'to_name' => $m->to_name,
                    'subject' => $m->subject,
                    'body' => $m->body,
                    'status' => $m->status,
                    'error' => $m->error,
                    'user_name' => $m->user?->name,
                    'sent_at' => $m->sent_at?->toIso8601String(),
                    'created_at' => $m->created_at?->toIso8601String(),
                    'read_at' => $m->read_at?->toIso8601String(),
                ])
                ->values()
            : collect();

        $unreadTotal = $threadList->sum('unread_count');

        return Inertia::render('Dashboard/CRM/Email', [
            'threads' => $threadList,
            'selected' => $selected ? $mapThread($selected) : null,
            'messages' => $messages,
            'stats' => [
                'threads' => $threadList->count(),
                'unread' => $unreadTotal,
                'registrations' => (int) $threadList->sum('invites_count'),
                'received_today' => CrmEmailMessage::query()
                    ->where('direction', 'inbound')
                    ->whereDate('created_at', today())
                    ->count(),
            ],
        ]);
    }

    public function compose(Request $request)
    {
        $data = $request->validate([
            'to_email' => 'required|email|max:255',
            'to_name' => 'nullable|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:20000',
        ]);

        $email = strtolower(trim($data['to_email']));
        $client = Client::whereRaw('LOWER(email) = ?', [$email])->first();

        $thread = CrmEmailThread::firstOrCreate(
            ['from_email' => $email],
            [
                'client_id' => $client?->id,
                'from_name' => $data['to_name'] ?? $client?->contact_person ?? $client?->company_name,
                'subject' => $data['subject'],
                'last_message_at' => now(),
            ],
        );

        if ($client && (! $thread->client_id || ! $thread->from_name)) {
            $thread->update([
                'client_id' => $thread->client_id ?: $client->id,
                'from_name' => $thread->from_name ?: ($client->contact_person ?: $client->company_name),
            ]);
        }

        [$status, $error] = $this->deliver(
            $email,
            $thread->from_name ?? $data['to_name'] ?? 'Client',
            $data['subject'],
            $data['body'],
        );

        CrmEmailMessage::create([
            'thread_id' => $thread->id,
            'user_id' => Auth::id(),
            'direction' => 'outbound',
            'type' => 'compose',
            'from_email' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'to_email' => $email,
            'to_name' => $data['to_name'] ?? $thread->from_name,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'status' => $status,
            'error' => $error,
            'sent_at' => now(),
            'read_at' => now(),
        ]);

        $thread->update([
            'subject' => $thread->subject ?: $data['subject'],
            'last_message_preview' => Str::limit(trim(strip_tags($data['body'])), 160),
            'last_message_at' => now(),
        ]);

        $redirect = redirect()->route('crm.email.index', ['thread' => $thread->hash_key]);

        return $status === 'sent'
            ? $redirect->with('message', "Email sent to {$email}.")
            : $redirect->withErrors(['email' => 'The email was saved in the inbox, but delivery failed: ' . $error]);
    }

    public function reply(Request $request, CrmEmailThread $thread)
    {
        $data = $request->validate([
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string|max:20000',
        ]);

        $recipient = $thread->from_email ?: $thread->client?->email;
        if (! $recipient) {
            return back()->withErrors(['email' => 'This thread has no recipient email address.']);
        }

        $subject = trim($data['subject'] ?? '') ?: $this->replySubject($thread->subject);

        [$status, $error] = $this->deliver(
            $recipient,
            $thread->from_name ?: ($thread->client?->contact_person ?? $thread->client?->company_name ?? 'Client'),
            $subject,
            $data['body'],
        );

        CrmEmailMessage::create([
            'thread_id' => $thread->id,
            'user_id' => Auth::id(),
            'direction' => 'outbound',
            'type' => 'reply',
            'from_email' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'to_email' => $recipient,
            'to_name' => $thread->from_name,
            'subject' => $subject,
            'body' => $data['body'],
            'status' => $status,
            'error' => $error,
            'sent_at' => now(),
            'read_at' => now(),
        ]);

        $thread->update([
            'subject' => $thread->subject ?: $subject,
            'last_message_preview' => Str::limit(trim(strip_tags($data['body'])), 160),
            'last_message_at' => now(),
        ]);

        return $status === 'sent'
            ? back()->with('message', "Reply sent to {$recipient}.")
            : back()->withErrors(['email' => 'The reply was saved, but delivery failed: ' . $error]);
    }

    public function sendRegistrationForm(Request $request, CrmEmailThread $thread)
    {
        $recipient = $thread->from_email ?: $thread->client?->email;
        if (! $recipient) {
            return back()->withErrors(['email' => 'This thread has no recipient email address.']);
        }

        $recipientName = $thread->from_name
            ?: ($thread->contact?->name ?? $thread->client?->contact_person ?? 'Business Partner');

        $invite = CrmRegistrationInvite::create([
            'token' => Str::random(64),
            'email' => $recipient,
            'name' => $recipientName,
            'thread_id' => $thread->id,
            'sent_by' => Auth::id(),
            'expires_at' => now()->addDays(30),
        ]);

        $registrationUrl = route('crm.registration.show', $invite->token);

        $status = 'sent';
        $error = null;
        try {
            Mail::to($recipient)->send(new RegistrationInviteMail(
                recipientName: $recipientName,
                companyName: $thread->client?->company_name,
                registrationUrl: $registrationUrl,
                senderName: Auth::user()?->name,
                expiresAt: $invite->expires_at?->format('F j, Y'),
            ));
        } catch (\Throwable $e) {
            $status = 'failed';
            $error = $e->getMessage();
            Log::warning('Registration invite email failed for ' . $recipient . ': ' . $error);
        }

        $subject = 'Complete your Monti Textiles partner registration';
        $body = "A partner registration form was sent to {$recipient}.\n"
            . "Registration link: {$registrationUrl}";

        CrmEmailMessage::create([
            'thread_id' => $thread->id,
            'user_id' => Auth::id(),
            'direction' => 'outbound',
            'type' => 'registration',
            'from_email' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'to_email' => $recipient,
            'to_name' => $recipientName,
            'subject' => $subject,
            'body' => $body,
            'status' => $status,
            'error' => $error,
            'sent_at' => now(),
            'read_at' => now(),
        ]);

        $thread->update([
            'registration_sent_at' => $status === 'sent' ? now() : $thread->registration_sent_at,
            'last_message_preview' => 'Registration form sent to ' . $recipient,
            'last_message_at' => now(),
        ]);

        return $status === 'sent'
            ? back()->with('message', "Registration form sent to {$recipient}.")
            : back()->withErrors(['email' => 'The registration form was prepared, but the email could not be sent: ' . $error]);
    }

    /**
     * Public webhook used by an inbound-mail service (Mailgun, Postmark,
     * Cloudflare Email Routing, …). Configure the shared secret with
     * CRM_EMAIL_INBOUND_KEY.
     */
    public function inbound(Request $request, string $key, InboundEmailImporter $importer)
    {
        $expected = (string) config('crm_email.inbound_key', 'monti-crm-inbound');
        if ($key === '' || ! hash_equals($expected, $key)) {
            abort(403);
        }

        $data = $request->validate([
            'from' => 'required|string|max:255',
            'from_name' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string|max:100000',
            'message_id' => 'nullable|string|max:255',
        ]);

        $stored = $importer->import($data);

        if ($stored === null) {
            return response()->json(['message' => 'Message already imported or sender is invalid.'], 422);
        }

        return response()->json([
            'ok' => true,
            'thread_id' => $stored['thread']->id,
            'message_id' => $stored['message']->id,
        ], 201);
    }

    /**
     * Manually re-scan the mailbox from the Email page.
     */
    public function sync(ImapInboxFetcher $fetcher)
    {
        $result = $fetcher->fetch();

        if ($result['error']) {
            return back()->withErrors(['sync' => 'Mailbox sync failed: ' . $result['error']]);
        }

        return back()->with('message', "Inbox synced — {$result['imported']} new email(s) imported.");
    }

    /**
     * Best-effort mailbox pull before rendering. Throttled so opening the page
     * does not hit IMAP on every click, and guarded so a bad mailbox
     * configuration never breaks the inbox itself.
     */
    protected function syncInboxIfDue(): void
    {
        $interval = (int) config('crm_email.imap.sync_interval', 60);
        if ($interval <= 0) {
            return;
        }

        // Cache::add() only returns true for the caller that acquired the
        // window, which doubles as a lightweight cross-request lock.
        if (! Cache::add('crm-email-imap-sync', now()->timestamp, $interval)) {
            return;
        }

        try {
            app(ImapInboxFetcher::class)->fetch();
        } catch (\Throwable $e) {
            Log::warning('CRM IMAP sync on page load failed: ' . $e->getMessage());
        }
    }

    protected function deliver(string $email, string $name, string $subject, string $body): array
    {
        try {
            Mail::to($email)->send(new CrmReplyMail(
                recipientName: $name ?: 'Business Partner',
                subjectLine: $subject,
                bodyText: $body,
                senderName: Auth::user()?->name,
            ));

            return ['sent', null];
        } catch (\Throwable $e) {
            Log::warning('CRM email delivery failed for ' . $email . ': ' . $e->getMessage());

            return ['failed', $e->getMessage()];
        }
    }

    protected function replySubject(?string $subject): string
    {
        $subject = trim((string) $subject);

        return $subject === '' ? 'Re: Monti Textiles' : 'Re: ' . preg_replace('/^(re:\s*)+/i', '', $subject);
    }
}
