<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\PresentsClientChat;
use App\Http\Controllers\Controller;
use App\Models\Crm\ClientCallSignal;
use App\Models\Crm\ClientMessage;
use App\Models\Crm\ClientMessageReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * The partner portal's direct line to Monti Textiles. One flat thread per
 * client company — no inquiry required. Mirrors the CRM inbox: text, files,
 * reply quotes, reactions and call/video logs.
 */
class ClientMessageController extends Controller
{
    use PresentsClientChat;

    protected const RING_TIMEOUT = 55;

    protected function clientId(): int
    {
        return (int) Auth::guard('client')->id();
    }

    protected function representativeId(): ?int
    {
        $id = (int) request()->session()->get('representative_id');

        return $id > 0 ? $id : null;
    }

    protected function viewerKey(): string
    {
        return 'client:' . ($this->representativeId() ?? 'owner');
    }

    protected function viewerName(): string
    {
        return 'You';
    }

    protected function visible()
    {
        return ClientMessage::query()->where('deleted_for_client', false);
    }

    protected function relations(): array
    {
        return [
            'senderUser:id,name',
            'client:id,company_name',
            'replyTo.senderUser:id,name',
            'replyTo.client:id,company_name',
            'reactions',
        ];
    }

    public function index()
    {
        $clientId = $this->clientId();

        $this->visible()
            ->where('client_id', $clientId)
            ->where('sender_type', 'crm')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return Inertia::render('Client/Messages', [
            'messages' => $this->thread($clientId),
            'callLogs' => $this->callLogs($clientId),
        ]);
    }

    public function feed(Request $request)
    {
        $clientId = $this->clientId();
        $afterId = (int) $request->query('after', 0);

        $this->expireStale($clientId);

        $messages = $this->visible()
            ->with($this->relations())
            ->where('client_id', $clientId)
            ->where(function ($query) use ($afterId) {
                $query->where('id', '>', $afterId)
                    ->orWhere(fn ($q) => $q->where('type', 'call')->where('created_at', '>=', now()->subHours(12)));
            })
            ->orderBy('id')
            ->limit(300)
            ->get()
            ->map(fn (ClientMessage $m) => $this->presentMessage($m, 'client', $this->viewerKey()));

        $this->visible()
            ->where('client_id', $clientId)
            ->where('sender_type', 'crm')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        // Monti Textiles is ringing us — surface it so the portal can show the
        // answer/decline prompt.
        $incoming = $this->visible()
            ->with($this->relations())
            ->where('client_id', $clientId)
            ->where('type', 'call')
            ->where('sender_type', 'crm')
            ->where('call_status', 'ringing')
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'messages' => $messages,
            'incoming' => $incoming ? $this->presentMessage($incoming, 'client', $this->viewerKey()) : null,
        ]);
    }

    /**
     * Global incoming-call feed for the partner: shows the answer prompt from
     * any page in the portal, not just the Messages thread.
     */
    public function incomingCalls()
    {
        $clientId = $this->clientId();
        $this->expireStale($clientId);

        $calls = $this->visible()
            ->where('client_id', $clientId)
            ->where('type', 'call')
            ->where('sender_type', 'crm')
            ->where('call_status', 'ringing')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (ClientMessage $m) => [
                'id' => $m->id,
                'type' => $m->call_type,
                'created_at' => $m->created_at,
                'client_id' => $m->client_id,
                'client_name' => null,
                'client_hash_key' => null,
            ]);

        return response()->json(['calls' => $calls]);
    }

    public function store(Request $request)
    {
        $clientId = $this->clientId();

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

        $replyToId = $this->validReplyId($clientId, $data['reply_to_id'] ?? null);
        $created = collect();

        foreach ($files as $file) {
            $path = $file->store('client-message-files/' . $clientId, 'public');
            $mime = $file->getClientMimeType();
            $isImage = str_starts_with((string) $mime, 'image/');

            $created->push(ClientMessage::create([
                'client_id' => $clientId,
                'sender_type' => 'client',
                'sender_contact_id' => $this->representativeId(),
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
                'client_id' => $clientId,
                'sender_type' => 'client',
                'sender_contact_id' => $this->representativeId(),
                'type' => 'text',
                'body' => $body,
                'reply_to_id' => $replyToId,
                'is_read' => false,
            ]));
        }

        $created->each->load($this->relations());
        $presented = $created->map(fn (ClientMessage $m) => $this->presentMessage($m, 'client', $this->viewerKey()));

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'messages' => $presented->values()->all(),
                'message' => $presented->last(),
            ], 201);
        }

        return back()->with('message', 'Message sent.');
    }

    public function unsendForMe(ClientMessage $message)
    {
        $this->authorizeThread($message);
        $message->update(['deleted_for_client' => true]);

        return response()->json(['ok' => true, 'id' => $message->id]);
    }

    public function unsendForEveryone(ClientMessage $message)
    {
        $this->authorizeThread($message);

        if ($message->sender_type !== 'client') {
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

        return response()->json(['message' => $this->presentMessage($message, 'client', $this->viewerKey())]);
    }

    public function toggleReaction(Request $request, ClientMessage $message)
    {
        $this->authorizeThread($message);

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
                'reactor_type' => 'client',
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

    public function startCall(Request $request)
    {
        $clientId = $this->clientId();
        $data = $request->validate(['type' => 'required|in:audio,video']);

        $message = ClientMessage::create([
            'client_id' => $clientId,
            'sender_type' => 'client',
            'sender_contact_id' => $this->representativeId(),
            'type' => 'call',
            'call_type' => $data['type'],
            'call_status' => 'ringing',
            'call_duration' => 0,
            'body' => $data['type'] === 'video' ? 'Video call' : 'Voice call',
            'is_read' => false,
        ]);

        $message->load($this->relations());

        return response()->json(['message' => $this->presentMessage($message, 'client', $this->viewerKey())], 201);
    }

    public function answerCall(Request $request, ClientMessage $message)
    {
        $this->authorizeThread($message);

        if (! $message->isCall()) {
            return response()->json(['message' => 'Not a call record.'], 422);
        }

        if ($message->call_status === 'ringing') {
            $message->update(['call_status' => 'answered']);
        }

        $message->load($this->relations());

        return response()->json(['message' => $this->presentMessage($message, 'client', $this->viewerKey())]);
    }

    public function signalCall(Request $request, ClientMessage $message)
    {
        $this->authorizeThread($message);

        $data = $request->validate([
            'kind' => 'required|in:offer,answer,candidate',
            'payload' => 'required|array',
        ]);

        ClientCallSignal::create([
            'client_message_id' => $message->id,
            'sender_side' => 'client',
            'kind' => $data['kind'],
            'payload' => $data['payload'],
        ]);

        return response()->json(['ok' => true], 201);
    }

    public function callSignals(Request $request, ClientMessage $message)
    {
        $this->authorizeThread($message);
        $this->expireSingle($message);

        $after = (int) $request->query('after', 0);

        $signals = ClientCallSignal::query()
            ->where('client_message_id', $message->id)
            ->where('sender_side', '!=', 'client')
            ->where('id', '>', $after)
            ->orderBy('id')
            ->get(['id', 'kind', 'payload']);

        $message->load($this->relations());

        return response()->json([
            'signals' => $signals,
            'last_id' => (int) ($signals->last()->id ?? $after),
            'message' => $this->presentMessage($message, 'client', $this->viewerKey()),
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

    public function endCall(Request $request, ClientMessage $message)
    {
        $this->authorizeThread($message);

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

        return response()->json(['message' => $this->presentMessage($message, 'client', $this->viewerKey())]);
    }

    protected function thread(int $clientId): array
    {
        return $this->visible()
            ->with($this->relations())
            ->where('client_id', $clientId)
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->map(fn (ClientMessage $m) => $this->presentMessage($m, 'client', $this->viewerKey()))
            ->all();
    }

    protected function callLogs(int $clientId): array
    {
        return $this->visible()
            ->with($this->relations())
            ->where('client_id', $clientId)
            ->where('type', 'call')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (ClientMessage $m) => $this->presentMessage($m, 'client', $this->viewerKey()))
            ->all();
    }

    protected function validReplyId(int $clientId, $replyToId): ?int
    {
        if (! $replyToId) {
            return null;
        }

        $exists = ClientMessage::where('id', (int) $replyToId)
            ->where('client_id', $clientId)
            ->exists();

        return $exists ? (int) $replyToId : null;
    }

    protected function authorizeThread(ClientMessage $message): void
    {
        abort_unless((int) $message->client_id === $this->clientId(), 404);
    }
}
