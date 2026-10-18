<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_call_signals')) {
            return;
        }

        // WebRTC signalling rows relayed over the existing HTTP polling channel
        // (no websocket media/signalling server required). Each participant
        // polls for rows they did not write.
        Schema::create('crm_call_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_call_id')->constrained('crm_calls')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();

            $table->string('kind', 20); // offer | answer | candidate
            $table->json('payload');

            $table->timestamp('created_at')->nullable();

            $table->index(['crm_call_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_call_signals');
    }
};
