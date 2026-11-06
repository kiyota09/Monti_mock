<?php

namespace App\Support;

use App\Models\Eco\ConversationMessage;
use Illuminate\Support\Facades\Cache;

class ConversationRealtime
{
    /**
     * Cache key for "<side> is typing" heartbeat.
     * $side is 'eco' or 'client'.
     */
    public static function typingKey(int $inquiryId, string $side): string
    {
        return "inquiry:{$inquiryId}:typing:{$side}";
    }

    public static function markTyping(int $inquiryId, string $side): void
    {
        Cache::put(self::typingKey($inquiryId, $side), true, now()->addSeconds(6));
    }

    public static function isTyping(int $inquiryId, string $side): bool
    {
        return (bool) Cache::get(self::typingKey($inquiryId, $side), false);
    }

    public static function clearTyping(int $inquiryId, string $side): void
    {
        Cache::forget(self::typingKey($inquiryId, $side));
    }

    /**
     * Fetch messages for polling. Returns only rows newer than $afterId
     * so the frontend can append without a full reload. The client portal
     * additionally hides lab→CRM handoffs until CRM forwards them.
     */
    public static function feed(int $inquiryId, int $afterId = 0, bool $onlyVisibleToClient = false)
    {
        $query = ConversationMessage::with(['attachments', 'sampleRequest.rounds']);

        $messages = $query
            ->where('inquiry_id', $inquiryId)
            ->when($onlyVisibleToClient, fn ($q) => $q->where('visible_to_client', true))
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')
            ->get();

        $messages->each(function (ConversationMessage $message) {
            $sample = $message->sampleRequest;
            if (! $sample) return;
            $sample->setAttribute('sample_fabric_design', $sample->fabricDesignForSample());
            $sample->setAttribute('sample_yarn_type', $sample->yarnTypeForSample());
            $sample->setAttribute('pantone_colors', collect($sample->pantone_colors)->map(fn ($color) => is_array($color) ? collect($color)->except(['formula', 'pantone_formula', 'recipe', 'recipe_id'])->all() : $color)->all());
            $sample->makeHidden(['formula', 'pantone_formula', 'recipe_id', 'formulations']);
            $sample->rounds->each(function ($round) {
                $round->setAttribute('sample_fabric_design', $round->fabricDesignForSample());
                $round->setAttribute('sample_yarn_type', $round->yarnTypeForSample());
                $round->setAttribute('pantone_colors', collect($round->pantone_colors)->map(fn ($color) => is_array($color) ? collect($color)->except(['formula', 'pantone_formula', 'recipe', 'recipe_id'])->all() : $color)->all());
                $round->makeHidden(['formula', 'pantone_formula', 'recipe_id', 'formulations']);
            });
        });

        return $messages;
    }
}
