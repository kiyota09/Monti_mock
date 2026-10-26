<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_email_messages')) {
            return;
        }

        Schema::create('crm_email_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('crm_email_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // inbound = received from the client, outbound = sent by CRM.
            $table->enum('direction', ['inbound', 'outbound'])->default('inbound');
            $table->string('type')->default('reply');

            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('to_email')->nullable();
            $table->string('to_name')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body');

            $table->string('message_id')->nullable();
            $table->string('in_reply_to')->nullable();

            $table->enum('status', ['received', 'sent', 'failed'])->default('sent');
            $table->text('error')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'id']);
            $table->index(['thread_id', 'direction', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_email_messages');
    }
};
