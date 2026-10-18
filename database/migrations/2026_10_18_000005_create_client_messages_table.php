<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_messages')) {
            return;
        }

        Schema::create('client_messages', function (Blueprint $table) {
            $table->id();

            // One flat, company-level thread per business partner.
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            // Who wrote it: CRM staff ("crm") or the partner portal ("client").
            $table->enum('sender_type', ['crm', 'client'])->default('crm');

            // A CRM staff member (sender_type = crm) …
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            // … or a partner-portal representative (sender_type = client).
            $table->foreignId('sender_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();

            $table->text('body');
            // Whether the *receiving* side has read this message.
            $table->boolean('is_read')->default(false);

            $table->timestamps();

            $table->index(['client_id', 'id']);
            $table->index(['client_id', 'sender_type', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_messages');
    }
};
