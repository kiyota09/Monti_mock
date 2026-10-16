<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Universal top-bar infrastructure for every internal account
     * (CEO, COO, Secretary, Special Officer, managers, supervisors, staff).
     *
     * - notifications: per-user feed (messenger arrivals, published memos,
     *   general alerts). The CEO executive inbox stays separate.
     * - message_threads + thread_participants + chat_messages: 1:1 and
     *   small-group internal messenger (polling — no socket server).
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 32)->default('general'); // message | memo | general
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link_route')->nullable();
            $table->boolean('is_read')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });

        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('thread_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['thread_id', 'user_id']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('thread_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('thread_participants');
        Schema::dropIfExists('message_threads');
        Schema::dropIfExists('notifications');
    }
};
