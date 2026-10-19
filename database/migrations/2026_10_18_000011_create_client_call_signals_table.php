<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_call_signals')) {
            return;
        }

        // WebRTC signalling for partner (CRM ↔ client) calls, relayed over the
        // existing polling feed. Each side polls for rows written by the other.
        Schema::create('client_call_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_message_id')->constrained('client_messages')->cascadeOnDelete();

            $table->string('sender_side', 10); // crm | client
            $table->string('kind', 20);        // offer | answer | candidate
            $table->json('payload');

            $table->timestamp('created_at')->nullable();

            $table->index(['client_message_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_call_signals');
    }
};
