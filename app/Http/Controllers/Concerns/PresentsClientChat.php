<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Crm\ClientMessage;
use Illuminate\Support\Str;

/**
 * Shared shaping of a direct-message row for both the CRM inbox and the
 * partner portal: attachments, reply quotes, call records and emoji
 * reactions, all rendered relative to the side that is looking at it.
 */
trait PresentsClientChat
{
    protected function presentMessage(ClientMessage $message, string $side, ?string $viewerKey): array
    {
        $unsent = $message->is_unsent;

        return [
            'id' => $message->id,
            'sender_type' => $message->sender_type,
            'sender_name' => $this->chatSenderName($message, $side),
            'type' => $message->type,
            'body' => $unsent ? null : $message->body,
            'is_read' => (bool) $message->is_read,
            'is_unsent' => $unsent,
            'created_at' => $message->created_at,
            'attachment' => (! $unsent && $message->attachment_path) ? [
                'url' => $message->attachment_url,
                'name' => $message->attachment_name,
                'mime' => $message->attachment_mime,
                'size' => (int) $message->attachment_size,
                'is_image' => (bool) $message->is_image,
            ] : null,
            'reply_to' => $unsent ? null : $this->replyPreview($message, $side),
            'reactions' => $this->reactionSummary($message, $viewerKey),
            'call' => $message->isCall() ? [
                'type' => $message->call_type,
                'status' => $message->call_status,
                'duration' => (int) $message->call_duration,
                'ended_at' => $message->call_ended_at,
            ] : null,
        ];
    }

    protected function chatSenderName(ClientMessage $message, string $side): string
    {
        if ($message->sender_type === 'crm') {
            return $message->senderUser?->name ?? 'Monti Textiles';
        }

        if ($side === 'client') {
            return 'You';
        }

        return $message->senderContact?->name ?? $message->client?->company_name ?? 'Business Partner';
    }

    protected function replyPreview(ClientMessage $message, string $side): ?array
    {
        if (! $message->reply_to_id) {
            return null;
        }

        $reply = $message->relationLoaded('replyTo') ? $message->replyTo : $message->replyTo()->first();
        if (! $reply) {
            return null;
        }

        return [
            'id' => $reply->id,
            'sender_name' => $this->chatSenderName($reply, $side),
            'is_unsent' => $reply->is_unsent,
            'preview' => $this->messagePreview($reply),
        ];
    }

    protected function messagePreview(ClientMessage $message): string
    {
        if ($message->is_unsent) {
            return 'This message was deleted';
        }

        if ($message->isCall()) {
            return $this->callLabel($message);
        }

        if ($message->attachment_path) {
            return $message->is_image ? 'Photo' : 'Attachment';
        }

        return Str::limit((string) $message->body, 80);
    }

    protected function callLabel(ClientMessage $message): string
    {
        return $message->call_type === 'video' ? 'Video call' : 'Voice call';
    }

    protected function lastPreview(?ClientMessage $message): ?string
    {
        return $message ? $this->messagePreview($message) : null;
    }

    protected function reactionSummary(ClientMessage $message, ?string $viewerKey): array
    {
        $reactions = $message->relationLoaded('reactions') ? $message->reactions : $message->reactions()->get();

        return $reactions
            ->groupBy('emoji')
            ->map(fn ($group, $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'mine' => $viewerKey !== null && $group->contains(fn ($r) => $r->reactor_key === $viewerKey),
            ])
            ->values()
            ->all();
    }
}
