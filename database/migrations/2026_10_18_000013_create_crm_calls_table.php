<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_calls')) {
            return;
        }

        Schema::create('crm_calls', function (Blueprint $table) {
            $table->id();

            // Internal CRM user-to-user call (CEO / COO / secretary / manager
            // / staff — anyone holding the Messaging page).
            $table->foreignId('caller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('callee_id')->constrained('users')->cascadeOnDelete();

            // Optional context when the call was started from a partner thread.
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();

            $table->enum('type', ['audio', 'video'])->default('audio');
            $table->enum('status', ['ringing', 'answered', 'declined', 'missed', 'completed', 'failed'])->default('ringing');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration')->default(0);

            $table->timestamps();

            $table->index(['callee_id', 'status']);
            $table->index(['caller_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_calls');
    }
};
