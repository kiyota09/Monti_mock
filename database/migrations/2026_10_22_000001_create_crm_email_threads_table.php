<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_email_threads')) {
            return;
        }

        Schema::create('crm_email_threads', function (Blueprint $table) {
            $table->id();

            // Best-effort links to the CRM records that own this mailbox thread.
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();

            // The external sender. One thread per sender address.
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('subject')->nullable();

            $table->text('last_message_preview')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('registration_sent_at')->nullable();

            $table->timestamps();

            $table->index('from_email');
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_email_threads');
    }
};
