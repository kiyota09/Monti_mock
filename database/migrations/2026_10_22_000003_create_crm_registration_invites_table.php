<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_registration_invites')) {
            return;
        }

        Schema::create('crm_registration_invites', function (Blueprint $table) {
            $table->id();
            $table->string('token', 80)->unique();
            $table->string('email');
            $table->string('name')->nullable();

            $table->foreignId('thread_id')->nullable()->constrained('crm_email_threads')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('crm_opportunities')->nullOnDelete();

            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_registration_invites');
    }
};
