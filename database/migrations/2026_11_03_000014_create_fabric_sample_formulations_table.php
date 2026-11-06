<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fabric_sample_formulations')) {
            Schema::create('fabric_sample_formulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fabric_sample_request_id')->constrained('fabric_sample_requests')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->json('formula');
            $table->string('attachment_path');
            $table->string('attachment_name');
            $table->string('attachment_mime', 120);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('awaiting_crm');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('conversation_message_id')->nullable()->constrained('conversation_messages')->nullOnDelete();
            $table->timestamps();
            });
        }

        Schema::table('fabric_sample_formulations', function (Blueprint $table) {
            $table->unique(['fabric_sample_request_id', 'version'], 'fsf_request_version_unique');
            $table->index(['status', 'created_at'], 'fsf_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_sample_formulations');
    }
};
