<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_meeting_requests')) {
            return;
        }

        Schema::create('crm_meeting_requests', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('opportunity_id')->nullable()->constrained('crm_opportunities')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('crm_activities')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type', 16)->default('meeting');       // call | meeting
            $table->string('summary')->nullable();
            $table->string('meeting_link', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('proposed_at')->nullable();

            $table->string('status', 16)->default('pending');     // pending | confirmed | declined | reschedule

            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();

            // Client's response (when they decline / request a reschedule).
            $table->string('response_place')->nullable();
            $table->text('response_message')->nullable();
            $table->timestamp('preferred_at')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('opportunity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_meeting_requests');
    }
};
