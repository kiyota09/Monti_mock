<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The lab's formulated-sample messages were stored as system events,
     * which both apps render as text-only center pills — no photo and no
     * approve/adjust buttons. They are content messages (photo + decision),
     * so flip existing photo-carrying sample messages back to regular
     * bubbles. Internal request notes without photos stay as pills.
     */
    public function up(): void
    {
        $photoMessageIds = DB::table('eco_conversation_attachments')
            ->distinct()
            ->pluck('conversation_message_id');

        DB::table('conversation_messages')
            ->whereIn('id', $photoMessageIds)
            ->whereNotNull('sample_request_id')
            ->where('is_system_event', true)
            ->update(['is_system_event' => false]);
    }

    public function down(): void
    {
        // Display-shape fix — no safe automatic reverse.
    }
};
