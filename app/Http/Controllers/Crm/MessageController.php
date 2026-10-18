<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Concerns\PresentsClientChat;
use App\Http\Controllers\Controller;
use App\Models\Crm\Client;
use App\Models\Crm\ClientCallSignal;
use App\Models\Crm\ClientMessage;
use App\Models\Crm\ClientMessageReaction;
use App\Support\RouteHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Direct messaging between CRM staff and business partners. Each partner
 * company has one flat thread; no inquiry is required. Threads carry text,
 * file/image attachments, reply quotes, emoji reactions and call/video logs.
 */
class MessageController extends Controller
{
    use PresentsClientChat;

    protected const RING_TIMEOUT = 55;

    protected function viewerKey(): string
    {
        return 'crm:' . (int) Auth::id();
    }

    protected function viewerName(): string
    {
        return Auth::user()?->name ?? 'Monti Textiles';
    }

    protected function visible()
    {
        return ClientMessage::query()->where('deleted_for_crm', false);
    }

    protected function relations(): array
    {
        return [
            'senderUser:id,name',
            'senderContact:id,name',
            'client:id,company_name',
            'replyTo.senderUser:id,name',
            'replyTo.senderContact:id,name',
            'replyTo.client:id,company_name',
            'reactions',
        ];
    }

    public function index(Request $request)
    {
        // Last message per thread (drives the inbox preview + ordering).
        $lastIds = $this->visible()
            ->select('client_id', DB::raw('MAX(id) as last_id'))
            ->groupBy('client_id')
            ->pluck('last_id', 'client_id');

        $lastMessages = $this->visible()
            ->whereIn('id', $lastIds->values()->all())
            ->get()
            ->keyBy('client_id');

        // Unread-from-partner counts (CRM's badge).
        $unreadCounts = $this->visible()
            ->where('sender_type', 'client')
            ->where('is_read', false)
            ->select('client_id', DB::raw('COUNT(*) as total'))
            ->groupBy('client_id')
            ->pluck('total', 'client_id');

        // Which thread to open: the querystring pick, else the one with the
        // oldest unread, else the most recently active thread.
        $selected = null;
        $key = $request->query('client');
        if (is_string($key) && $key !== '') {
            $id = RouteHash::decode($key, 'client');
            $selected = $id ? Client::find($id) : null;
        }

        if (! $selected) {
            $preferredId = $unreadCounts->keys()->first()
                ?? $lastIds->sortByDesc(fn ($id) => $id)->keys()->first();
            $selected = $preferredId
                ? Client::find($preferredId)
                : Client::where('status', 'active')->orderBy('company_name')->first();
        }

        // Opening a thread clears the partner's unread messages.
        if ($selected) {
            $this->visible()
                ->where('client_id', $selected->id)
                ->where('sender_type', 'client')
                ->where('is_read', false)
                ->update(['is_read' => true]);

            $unreadCounts->forget($selected->id);
        }

        $clients = Client::where('status', 'active')
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'contact_person', 'email'])
            ->map(function (Client $client) use ($unreadCounts, $lastMessages) {
                $last = $lastMessages->get($client->id);

                return [
                    'id' => $client->id,
                    'hash_key' => $client->hash_key,
                    'company_name' => $client->company_name,
                    'contact_person' => $client->contact_person,
                    'email' => $client->email,
                    'unread' => (int) ($unreadCounts[$client->id] ?? 0),
                    'last_message' => $this->lastPreview($last),
                    'last_sender' => $last?->sender_type,
                    'last_type' => $last?->type,
                    'last_at' => $last?->created_at,
                    'last_id' => (int) ($last?->id ?? 0),
                ];
            })
            ->sort(function (array $a, array $b) {
                if ($a['last_id'] && $b['last_id']) {
                    return $b['last_id'] <=> $a['last_id'];
                }
                if ($a['last_id']) {
                    return -1;
                }
                if ($b['last_id']) {
                    return 1;
                }

                return strcmp($a['company_name'], $b['company_name']);
            })
            ->values();

        $messages = $selected
            ? $this->visible()
                ->with($this->relations())
                ->where('client_id', $selected->id)
                ->orderBy('id')
                ->limit(500)
                ->get()
                ->map(fn (ClientMessage $m) => $this->presentMessage($m, 'crm', $this->viewerKey()))
            : [];

        $callLogs = $selected
            ? $this->visible()
                ->with($this->relations())
                ->where('client_id', $selected->id)
                ->where('type', 'call')
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (ClientMessage $m) => $this->presentMessage($m, 'crm', $this->viewerKey()))
            : [];

        return Inertia::render('Dashboard/CRM/Messages', [
            'clients' => $clients,
            'selected' => $selected ? [
                'id' => $selected->id,
                'hash_key' => $selected->hash_key,
                'company_name' => $selected->company_name,
                'contact_person' => $selected->contact_person,
                'email' => $selected->email,
                'phone' => $selected->phone,
            ] : null,
            'messages' => $messages,
            'callLogs' => $callLogs,
            'totalUnread' => (int) $unreadCounts->sum(),
        ]);
    }

    public function feed(Request $request, Client $client)
    {
        $afterId = (int) $request->query('after', 0);

        $this->expireStale($client->id);

        $messages = $this->visible()
            ->with($this->relations())
            ->where('client_id', $client->id)
            ->where(function ($query) use ($afterId) {
                $query->where('id', '>', $afterId)
                    ->orWhere(fn ($q) => $q->where('type', 'call')->where('created_at', '>=', now()->subHours(12)));
            })
            ->orderBy('id')
            ->limit(300)
            ->get()
            ->map(fn (ClientMessage $m) => $this->presentMessage($m, 'crm', $this->viewerKey()));

        // Staff is viewing the thread — from the partner's perspective this
        // message has now been read.
        $this->visible()
            ->where('client_id', $client->id)
            ->where('sender_type', 'client')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        // A partner is ringing us for this thread — surface it so the inbox can
        // show the answer/decline prompt without a dedicated push channel.
        $incoming = $this->visible()
            ->with($this->relations())
            ->where('client_id', $client->id)
            ->where('type', 'call')
            ->where('sender_type', 'client')
            ->where('call_status', 'ringing')
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'messages' => $messages,
            'incoming' => $incoming ? $this->presentMessage($incoming, 'crm', $this->viewerKey()) : null,
        ]);
    }

    /**
     * Global incoming-call feed for staff: any partner ringing any thread, so
     * the call-centre overlay can prompt from any dashboard page.
     */
    public function incomingCalls()
    {
        $this->expireStaleAll();

        $calls = $this->visible()
            ->with('client:id,company_name')
            ->where('type', 'call')
            ->where('sender_type', 'client')
            ->where('call_status', 'ringing')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (ClientMessage $m) => [
                'id' => $m->id,
                'type' => $m->call_type,
                'created_at' => $m->created_at,
                'client_id' => $m->client_id,
                'client_name' => $m->client?->company_name,
                'client_hash_key' => $m->client?->hash_key,
            ]);

        return response()->json(['calls' => $calls]);
    }

    public function store(Request $request, Client $client)
    {
        $data = $request->validate([
            'body' => 'nullable|string|max:5000',
            'reply_to_id' => 'nullable|integer',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,png,jpg,jpeg,webp,gif,svg|max:10240',
        ]);

        $body = trim((string) ($data['body'] ?? ''));
        $files = $request->file('files') ?? [];

        if ($body === '' && count($files) === 0) {
            return response()->json(['message' => 'Enter a message or attach a file.'], 422);
        }

        $replyToId = $this->validReplyId($client, $data['reply_to_id'] ?? null);
        $created = collect();

        foreach ($files as $file) {
            $path = $file->store('crm-message-files/' . $client->id, 'public');
            $mime = $file->getClientMimeType();
            $isImage = str_starts_with((string) $mime, 'image/');

            $created->push(ClientMessage::create([
                'client_id' => $client->id,
                'sender_type' => 'crm',
                'sender_user_id' => Auth::id(),
                'type' => $isImage ? 'image' : 'file',
                'body' => '',
                'attachment_path' => $path,
                'attachment_name' => $file->getClientOriginalName(),
                'attachment_mime' => $mime,
                'attachment_size' => $file->getSize() ?: 0,
                'reply_to_id' => $replyToId,
                'is_read' => false,
            ]));
        }

        if ($body !== '') {
            $created->push(ClientMessage::create([
                'client_id' => $client->id,
                'sender_type' => 'crm',
                'sender_user_id' => Auth::id(),
                'type' => 'text',
                'body' => $body,
                'reply_to_id' => $replyToId,
                'is_read' => false,
            ]));
        }

        $created->each->load($this->relations());
        $presented = $created->map(fn (ClientMessage $m) => $this->presentMessage($m, 'crm', $this->viewerKey()));

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'messages' => $presented->values()->all(),
                'message' => $presented->last(),
            ], 201);
        }

        return back()->with('message', 'Message sent.');
    }

    public function unsendForMe(Client $client, ClientMessage $message)
    {
        $this->authorizeThread($client, $message);
        $message->update(['deleted_for_crm' => true]);

        return response()->json(['ok' => true, 'id' => $message->id]);
    }

    public function unsendForEveryone(Client $client, ClientMessage $message)
    {
        $this->authorizeThread($client, $message);

        if ($message->sender_type !== 'crm') {
            return response()->json(['message' => 'You can only unsend your own messages.'], 403);
        }

        $message->update([
            'deleted_for_everyone_at' => now(),
            'body' => '',
            'attachment_path' => null,
            'attachment_name' => null,
            'attachment_mime' => null,
            'attachment_size' => null,
        ]);
        $message->reactions()->delete();
        $message->load($this->relations());

        return response()->json(['message' => $this->presentMessage($message, 'crm', $this->viewerKey())]);
    }

    public function toggleReaction(Request $request, Client $client, ClientMessage $message)
    {
        $this->authorizeThread($client, $message);

        $data = $request->validate(['emoji' => 'required|string|max:16']);
        $key = $this->viewerKey();

        $existing = ClientMessageReaction::where('client_message_id', $message->id)
            ->where('reactor_key', $key)
            ->where('emoji', $data['emoji'])
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            ClientMessageReaction::create([
                'client_message_id' => $message->id,
                'reactor_type' => 'crm',
                'reactor_key' => $key,
                'reactor_name' => $this->viewerName(),
                'emoji' => $data['emoji'],
            ]);
        }

        $message->load('reactions');

        return response()->json([
            'message_id' => $message->id,
            'reactions' => $this->reactionSummary($message, $key),
        ]);
    }

    public function startCall(Request $request, Client $client)
    {
        $data = $request->validate(['type' => 'required|in:audio,video']);

        $message = ClientMessage::create([
            'client_id' => $client->id,
            'sender_type' => 'crm',
            'sender_user_id' => Auth::id(),
            'type' => 'call',
            'call_type' => $data['type'],
            'call_status' => 'ringing',
            'call_duration' => 0,
            'body' => $data['type'] === 'video' ? 'Video call' : 'Voice call',
            'is_read' => false,
        ]);

        $message->load($this->relations());

        return response()->json(['message' => $this->presentMessage($message, 'crm', $this->viewerKey())], 201);
    }

    public function answerCall(Request $request, Client $client, ClientMessage $message)
    {
        $this->authorizeThread($client, $message);

        if (! $message->isCall()) {
            return response()->json(['message' => 'Not a call record.'], 422);
        }

        if ($message->call_status === 'ringing') {
            $message->update(['call_status' => 'answered']);
        }

        $message->load($this->relations());

        return response()->json(['message' => $this->presentMessage($message, 'crm', $this->viewerKey())]);
    }

    public function signalCall(Request $request, Client $client, ClientMessage $message)
    {
        $this->authorizeThread($client, $message);

        $data = $request->validate([
            'kind' => 'required|in:offer,answer,candidate',
            'payload' => 'required|array',
        ]);

        ClientCallSignal::create([
            'client_message_id' => $message->id,
            'sender_side' => 'crm',
            'kind' => $data['kind'],
            'payload' => $data['payload'],
        ]);

        return response()->json(['ok' => true], 201);
    }

    public function callSignals(Request $request, Client $client, ClientMessage $message)
    {
        $this->authorizeThread($client, $message);
        $this->expireSingle($message);

        $after = (int) $request->query('after', 0);

        $signals = ClientCallSignal::query()
            ->where('client_message_id', $message->id)
            ->where('sender_side', '!=', 'crm')
            ->where('id', '>', $after)
            ->orderBy('id')
            ->get(['id', 'kind', 'payload']);

        $message->load($this->relations());

        return response()->json([
            'signals' => $signals,
            'last_id' => (int) ($signals->last()->id ?? $after),
            'message' => $this->presentMessage($message, 'crm', $this->viewerKey()),
        ]);
    }

    protected function expireStale(int $clientId): void
    {
        $this->visible()
            ->where('client_id', $clientId)
            ->where('type', 'call')
            ->where('call_status', 'ringing')
            ->where('created_at', '<', now()->subSeconds(self::RING_TIMEOUT))
            ->update(['call_status' => 'missed', 'call_ended_at' => now()]);
    }

    protected function expireSingle(ClientMessage $message): void
    {
        if ($message->isCall()
            && $message->call_status === 'ringing'
            && $message->created_at
            && $message->created_at->lt(now()->subSeconds(self::RING_TIMEOUT))) {
            $message->update(['call_status' => 'missed', 'call_ended_at' => now()]);
        }
    }

    protected function expireStaleAll(): void
    {
        $this->visible()
            ->where('type', 'call')
            ->where('call_status', 'ringing')
            ->where('created_at', '<', now()->subSeconds(self::RING_TIMEOUT))
            ->update(['call_status' => 'missed', 'call_ended_at' => now()]);
    }

    public function endCall(Request $request, Client $client, ClientMessage $message)
    {
        $this->authorizeThread($client, $message);

        if (! $message->isCall()) {
            return response()->json(['message' => 'Not a call record.'], 422);
        }

        $data = $request->validate([
            'status' => 'nullable|in:completed,missed,declined,failed',
            'duration' => 'nullable|integer|min:0',
        ]);

        $message->update([
            'call_status' => $data['status'] ?? 'completed',
            'call_duration' => $data['duration'] ?? 0,
            'call_ended_at' => now(),
        ]);
        $message->load($this->relations());

        return response()->json(['message' => $this->presentMessage($message, 'crm', $this->viewerKey())]);
    }

    protected function validReplyId(Client $client, $replyToId): ?int
    {
        if (! $replyToId) {
            return null;
        }

        $exists = ClientMessage::where('id', (int) $replyToId)
            ->where('client_id', $client->id)
            ->exists();

        return $exists ? (int) $replyToId : null;
    }

    protected function authorizeThread(Client $client, ClientMessage $message): void
    {
        abort_unless((int) $message->client_id === (int) $client->id, 404);
    }
}
