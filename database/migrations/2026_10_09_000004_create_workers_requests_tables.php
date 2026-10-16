<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agency workers (Pages 5-7) + manpower requests (Pages 8-10).
     */
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->string('name');
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('phone', 60)->nullable();
            $table->text('address')->nullable();
            $table->string('position_skill')->nullable();
            $table->decimal('daily_rate', 12, 2)->nullable();
            $table->string('gov_id_number', 100)->nullable()->unique();
            $table->date('clearance_expiry')->nullable();
            $table->string('emergency_name')->nullable();
            $table->string('emergency_phone', 60)->nullable();
            $table->string('status', 20)->default('Available'); // Available|Deployed|Inactive|Blacklisted
            $table->text('blacklist_reason')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'status']);
        });

        Schema::create('manpower_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('department');
            $table->string('position_skill');
            $table->unsignedInteger('headcount')->default(1);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('shift')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            // Stored lifecycle: Draft|Open|Cancelled|Closed. Partially filled
            // and Filled are derived from assigned vs needed (see model).
            $table->string('status', 20)->default('Draft');
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'status']);
        });

        Schema::create('manpower_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('manpower_requests')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assigned_by_name')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['request_id', 'released_at']);
            $table->index(['worker_id', 'released_at']);
        });

        // Worker/request events share the agency activity trail.
        Schema::table('agency_activities', function (Blueprint $table) {
            $table->foreignId('worker_id')->nullable()->after('agency_id')->constrained('workers')->nullOnDelete();
            $table->foreignId('request_id')->nullable()->after('worker_id')->constrained('manpower_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agency_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('request_id');
            $table->dropConstrainedForeignId('worker_id');
        });
        Schema::dropIfExists('manpower_assignments');
        Schema::dropIfExists('manpower_requests');
        Schema::dropIfExists('workers');
    }
};
