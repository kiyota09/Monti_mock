<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('position_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->string('requester_role')->default('CEO'); // President / Vice President context
            $table->string('current_position');
            $table->string('requested_position'); // manager, staff, secretary, special_officer, vice_president
            $table->string('current_role')->nullable();
            $table->string('requested_role')->nullable(); // home module when relevant
            $table->string('action'); // promote | demote
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'fulfilled', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'target_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_change_requests');
    }
};
