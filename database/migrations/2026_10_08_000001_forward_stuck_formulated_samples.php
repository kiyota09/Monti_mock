<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Align pre-existing fabric sample rows with the direct-to-client rule.
     *
     * An earlier iteration kept the lab's formulated result hidden until CRM
     * forwarded it. The rule now is: the formulated sample (formula + photo)
     * goes straight to the client for approve / adjust, so rows still stuck
     * at `formulated` move to `forwarded` and their photo message becomes
     * visible. Internal request messages (no photo attached) stay hidden.
     */
    public function up(): void
    {
        $ids = DB::table('fabric_sample_requests')
            ->where('status', 'formulated')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $photoMessageIds = DB::table('eco_conversation_attachments')
            ->whereIn('conversation_message_id', function ($q) use ($ids) {
                $q->select('id')->from('conversation_messages')
                    ->whereIn('sample_request_id', $ids);
            })
            ->distinct()
            ->pluck('conversation_message_id');

        DB::table('conversation_messages')
            ->whereIn('id', $photoMessageIds)
            ->update(['visible_to_client' => true]);

        DB::table('fabric_sample_requests')
            ->whereIn('id', $ids)
            ->update(['status' => 'forwarded', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // One-way alignment with the current visibility rule — no reverse.
    }
};
