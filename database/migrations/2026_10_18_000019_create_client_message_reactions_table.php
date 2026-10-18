<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_message_reactions')) {
            return;
        }

        Schema::create('client_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_message_id')->constrained('client_messages')->cascadeOnDelete();

            // Who reacted: CRM staff ("crm") or the partner portal ("client").
            $table->string('reactor_type', 10);
            // Stable per-identity key (e.g. "crm:5", "client:3", "client:owner")
            // so one person can toggle each emoji exactly once.
            $table->string('reactor_key', 40);
            $table->string('reactor_name')->nullable();
            $table->string('emoji', 16);

            $table->timestamps();

            $table->unique(['client_message_id', 'reactor_key', 'emoji'], 'cmr_unique');
            $table->index('client_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_message_reactions');
    }
};
