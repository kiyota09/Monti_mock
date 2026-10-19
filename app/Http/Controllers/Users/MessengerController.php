<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Core\ChatMessage;
use App\Models\Core\MessageThread;
use App\Models\Core\Notification;
use App\Models\Core\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessengerController extends Controller
{
    protected function threadFor(Request $request, MessageThread $thread): MessageThread
    {
        abort_unless(
            DB::table('thread_participants')->where('thread_id', $thread->id)->where('user_id', $request->user()->id)->exists(),
            403
        );

        return $thread;
    }

    /**
     * Human-readable list preview. Image/file-only messages have an
     * empty body, so fall back to a 📷/📎 label instead of
     * "No messages yet".
     */
    protected function previewFor(ChatMessage $m): string
    {
        $body = trim((string) ($m->body ?? ''));
        if ($body !== '') {
            return mb_substr($body, 0, 80);
        }
        if (!empty($m->file_path) || !empty($m->file_url)) {
            $isImage = $m->is_image ?? (bool) preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', (string) ($m->file_name ?? $m->file_path ?? ''));
            return $isImage ? '📷 Photo' : '📎 Attachment' . (!empty($m->file_name) ? ': ' . $m->file_name : '');
        }
        return '📎 Attachment';
    }

    /**
     * Standalone full-page Messenger (opened in a new tab from the top bar).
     * The page hydrates from the JSON endpoints below; it merely needs to
     * render under the auth guard.
     */
    public function page(Request $request)
    {
        return inertia('Users/Messenger');
    }

    public function threads(Request $request)
    {
        $me = $request->user()->id;

        $threads = MessageThread::with(['participants:id,name,role,position,profile_photo_path', 'latestMessage.sender:id,name'])
            ->whereIn('id', DB::table('thread_participants')->where('user_id', $me)->pluck('thread_id'))
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'kind' => $t->isGroup() ? 'group' : 'personal',
                'title' => $t->displayTitle($me),
                'photo' => $t->photo_url,
                'member_count' => $t->participants->count(),
                'created_by' => $t->created_by,
                'participants' => $t->participants->map(fn ($p) => [
                    'id' => $p->id, 'name' => $p->name, 'role' => $p->role, 'position' => $p->position,
                    'photo' => $p->profile_photo_path ? '/storage/'.ltrim($p->profile_photo_path, '/') : null,
                ])->values(),
                'preview' => $t->latestMessage ? $this->previewFor($t->latestMessage) : null,
                'preview_by' => $t->latestMessage?->sender?->name,
                'at' => $t->latestMessage?->created_at ?? $t->created_at,
                'unread' => $t->unreadFor($me),
            ]);

        return response()->json(['threads' => $threads]);
    }

    public function show(Request $request, MessageThread $thread)
    {
        $this->threadFor($request, $thread);

        $messages = $thread->messages()->with('sender:id,name,profile_photo_path')->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'mine' => $m->sender_id === $request->user()->id,
                'sender' => $m->sender?->name,
                'sender_photo' => $m->sender?->profile_photo_path ? '/storage/'.ltrim($m->sender->profile_photo_path, '/') : null,
                'at' => $m->created_at,
                'file_url' => $m->file_url,
                'file_name' => $m->file_name,
                'is_image' => $m->is_image,
            ]);

        DB::table('thread_participants')
            ->where('thread_id', $thread->id)
            ->where('user_id', $request->user()->id)
            ->update(['last_read_at' => now()]);

        $thread->load('participants:id,name,role,position,profile_photo_path');

        return response()->json([
            'thread' => [
                'id' => $thread->id,
                'kind' => $thread->isGroup() ? 'group' : 'personal',
                'title' => $thread->displayTitle($request->user()->id),
                'photo' => $thread->photo_url,
                'created_by' => $thread->created_by,
                'members' => $thread->participants->map(fn ($p) => [
                    'id' => $p->id, 'name' => $p->name, 'role' => $p->role, 'position' => $p->position,
                    'photo' => $p->profile_photo_path ? '/storage/'.ltrim($p->profile_photo_path, '/') : null,
                ])->values(),
            ],
            'messages' => $messages,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_ids' => 'required|array|min:1|max:8',
            'user_ids.*' => 'exists:users,id',
            'title' => 'nullable|string|max:120',
            'body' => 'required|string|max:2000',
        ]);

        $me = $request->user()->id;
        $otherIds = collect($data['user_ids'])->reject(fn ($id) => (int) $id === $me)->unique()->values();

        abort_if($otherIds->isEmpty(), 422, 'Choose at least one other person.');
        abort_unless(User::whereIn('id', $otherIds)->where('is_active', true)->count() === $otherIds->count(), 422, 'One or more recipients are inactive.');

        // Reuse an existing untitled 1:1 thread instead of duplicating it.
        if ($otherIds->count() === 1 && empty($data['title'])) {
            $mine = DB::table('thread_participants')->where('user_id', $me)->pluck('thread_id');
            $existing = MessageThread::whereIn('id', $mine)
                ->whereNull('title')
                ->withCount('participants')
                ->whereHas('participants', fn ($q) => $q->where('users.id', $otherIds[0]))
                ->get()
                ->firstWhere(fn ($t) => $t->participants_count === 2);
            if ($existing) {
                $this->deliver($existing, $me, $data['body']);

                return response()->json(['thread_id' => $existing->id, 'reused' => true]);
            }
        }

        $thread = DB::transaction(function () use ($me, $otherIds, $data) {
            $thread = MessageThread::create([
                'title' => $data['title'] ?? null,
                'created_by' => $me,
                'last_message_at' => now(),
            ]);
            $thread->participants()->attach($otherIds->push($me)->all());

            return $thread;
        });

        $this->deliver($thread, $me, $data['body']);

        return response()->json(['thread_id' => $thread->id]);
    }

    public function send(Request $request, MessageThread $thread)
    {
        $this->threadFor($request, $thread);

        $data = $request->validate([
            'body' => 'nullable|string|max:2000|required_without:file',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf|max:10240',
        ]);

        $filePath = $fileName = $fileType = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filePath = $file->store('chat-files', 'public');
            $fileName = $file->getClientOriginalName();
            $fileType = $file->getMimeType();
        }

        $message = $this->deliver($thread, $request->user()->id, $data['body'] ?? '', $filePath, $fileName, $fileType);

        return response()->json([
            'message' => $this->serializeMessage($message, $request->user()->id, $request->user()->name),
        ]);
    }

    protected function serializeMessage(ChatMessage $message, int $viewerId, ?string $viewerName = null): array
    {
        $message->loadMissing('sender:id,name,profile_photo_path');

        return [
            'id' => $message->id,
            'body' => $message->body,
            'mine' => $message->sender_id === $viewerId,
            'sender' => $message->sender?->name ?? $viewerName,
            'sender_photo' => $message->sender?->profile_photo_path ? '/storage/'.ltrim($message->sender->profile_photo_path, '/') : null,
            'at' => $message->created_at,
            'file_url' => $message->file_url,
            'file_name' => $message->file_name,
            'is_image' => $message->is_image,
        ];
    }

    protected function deliver(MessageThread $thread, int $senderId, string $body = '', ?string $filePath = null, ?string $fileName = null, ?string $fileType = null): ChatMessage
    {
        return DB::transaction(function () use ($thread, $senderId, $body, $filePath, $fileName, $fileType) {
            $message = ChatMessage::create([
                'thread_id' => $thread->id, 'sender_id' => $senderId, 'body' => $body ?: null,
                'file_path' => $filePath, 'file_name' => $fileName, 'file_type' => $fileType,
            ]);
            $thread->update(['last_message_at' => now()]);

            $sender = User::find($senderId);
            $others = DB::table('thread_participants')->where('thread_id', $thread->id)->where('user_id', '!=', $senderId)->pluck('user_id');
            $preview = $body !== '' ? mb_substr($body, 0, 120) : ('Sent a file'.($fileName ? ': '.$fileName : ''));
            foreach ($others as $uid) {
                Notification::notify($uid, 'message', 'New message from '.$sender?->name, $preview, null, $senderId);
            }

            return $message;
        });
    }

    /**
     * Group settings: rename and/or replace/remove the group photo.
     * Personal (1:1 untitled) threads cannot be renamed.
     */
    public function update(Request $request, MessageThread $thread)
    {
        $this->threadFor($request, $thread);

        $data = $request->validate([
            'title' => 'nullable|string|max:120',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'remove_photo' => 'nullable|boolean',
        ]);

        if (! $thread->isGroup() && ! empty($data['title'])) {
            abort(422, 'Personal chats cannot be renamed.');
        }

        if ($request->hasFile('photo')) {
            if ($thread->photo_path) {
                \Storage::disk('public')->delete($thread->photo_path);
            }
            $thread->photo_path = $request->file('photo')->store('chat-photos', 'public');
        } elseif (! empty($data['remove_photo'])) {
            if ($thread->photo_path) {
                \Storage::disk('public')->delete($thread->photo_path);
            }
            $thread->photo_path = null;
        }
        if (array_key_exists('title', $data)) {
            $thread->title = $data['title'] ?: null;
        }
        $thread->save();

        return response()->json(['ok' => true, 'title' => $thread->displayTitle($request->user()->id), 'photo' => $thread->photo_url]);
    }

    /**
     * Add employees to a group chat.
     */
    public function addMembers(Request $request, MessageThread $thread)
    {
        $this->threadFor($request, $thread);
        abort_unless($thread->isGroup(), 422, 'Members can only be added to group chats.');

        $data = $request->validate([
            'user_ids' => 'required|array|min:1|max:8',
            'user_ids.*' => 'exists:users,id',
        ]);

        $current = DB::table('thread_participants')->where('thread_id', $thread->id)->pluck('user_id')->all();
        $add = User::whereIn('id', $data['user_ids'])->where('is_active', true)
            ->pluck('id')->reject(fn ($id) => in_array($id, $current))->values();
        abort_if($add->isEmpty(), 422, 'Everyone selected is already in this group.');

        $thread->participants()->attach($add->all());

        return response()->json(['ok' => true, 'added' => $add->count()]);
    }

    /**
     * Remove an employee from a group chat (any member may remove others;
     * removing yourself is leaving — use leave() so empty groups close).
     */
    public function removeMember(Request $request, MessageThread $thread, int $user)
    {
        $this->threadFor($request, $thread);
        abort_unless($thread->isGroup(), 422, 'Only group chats have removable members.');
        abort_if($user === $request->user()->id, 422, 'Use Leave instead to exit the group yourself.');

        $exists = DB::table('thread_participants')->where('thread_id', $thread->id)->where('user_id', $user)->exists();
        abort_unless($exists, 404, 'That employee is not in this group.');

        $thread->participants()->detach($user);

        return response()->json(['ok' => true]);
    }

    /**
     * Leave a group chat. The last member out closes (deletes) the group.
     */
    public function leave(Request $request, MessageThread $thread)
    {
        $this->threadFor($request, $thread);
        abort_unless($thread->isGroup(), 422, 'Personal chats cannot be left — they simply stay.');

        $thread->participants()->detach($request->user()->id);

        $remaining = DB::table('thread_participants')->where('thread_id', $thread->id)->count();
        if ($remaining === 0) {
            if ($thread->photo_path) {
                \Storage::disk('public')->delete($thread->photo_path);
            }
            $thread->delete();

            return response()->json(['ok' => true, 'closed' => true]);
        }

        return response()->json(['ok' => true, 'closed' => false]);
    }
}
