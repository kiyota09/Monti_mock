<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * V4 attendance + billing backend.
     * - attendances: per worker per day (unique), Draft → Approved/Rejected.
     * - attendance_adjustments: reason-gated corrections to Approved rows.
     * - billing_statements: Draft → Issued → Paid (Cancelled releases).
     * - manpower_proposals: agency-proposed workers awaiting HR review.
     * - worker_rate_requests: agency-requested rate changes awaiting HR.
     * - agency_users.must_change_password: forced change at first login.
     */
    public function up(): void
    {
        // Billing statements first: attendances reference them (billed mark).
        Schema::create('billing_statements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('Draft'); // Draft|Issued|Paid|Cancelled
            $table->decimal('labor_cost', 15, 2)->default(0);
            $table->decimal('service_fee', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->date('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'status']);
        });

        Schema::create('agency_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->foreignId('request_id')->constrained('manpower_requests')->cascadeOnDelete();
            $table->date('date');
            $table->string('time_in', 5)->nullable();
            $table->string('time_out', 5)->nullable();
            $table->decimal('hours', 6, 2)->nullable();
            $table->decimal('overtime_hours', 6, 2)->default(0);
            $table->string('status', 20)->default('Present'); // Present|Absent|Late|Half day|Leave
            $table->string('approval', 20)->default('Draft'); // Draft|Approved|Rejected
            $table->text('review_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            // Agency confirmation of Approved rows (3-day window).
            $table->string('agency_confirmation', 20)->default('Pending'); // Pending|Confirmed|Disputed
            $table->text('dispute_reason')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->string('disputed_by_name')->nullable();
            // Rate copied at approval so later changes never alter billing.
            $table->decimal('rate_snapshot', 12, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('billed_statement_id')->nullable()->constrained('billing_statements')->nullOnDelete();
            $table->timestamps();
            $table->unique(['worker_id', 'date']);
            $table->index(['request_id', 'date']);
        });

        Schema::create('attendance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('agency_attendances')->cascadeOnDelete();
            $table->json('changes')->nullable();
            $table->text('reason');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->timestamps();
        });

        Schema::create('manpower_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('manpower_requests')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->string('status', 20)->default('Proposed'); // Proposed|Accepted|Rejected
            $table->foreignId('proposed_by')->nullable();
            $table->string('proposed_by_name')->nullable();
            $table->text('review_reason')->nullable();
            $table->string('reviewed_by_name')->nullable();
            $table->timestamps();
            $table->index(['request_id', 'status']);
        });

        Schema::create('worker_rate_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->decimal('old_rate', 12, 2)->nullable();
            $table->decimal('new_rate', 12, 2);
            $table->string('status', 20)->default('Pending'); // Pending|Approved|Rejected
            $table->string('requested_by_name')->nullable();
            $table->text('review_reason')->nullable();
            $table->string('reviewed_by_name')->nullable();
            $table->timestamps();
            $table->index(['worker_id', 'status']);
        });

        Schema::table('agency_users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('is_active');
        });

        Schema::table('agencies', function (Blueprint $table) {
            // Termination timestamp: final attendance/billing for terminated
            // agencies is only allowed for work on/before this date.
            $table->timestamp('terminated_at')->nullable()->after('status');
        });

        Schema::table('workers', function (Blueprint $table) {
            // Agency-added workers wait for HR approval before Available.
            $table->string('pending_note')->nullable()->after('blacklist_reason');
        });

        // Statement events share the agency activity trail (worker_id and
        // request_id were added by the workers/requests migration).
        Schema::table('agency_activities', function (Blueprint $table) {
            if (! Schema::hasColumn('agency_activities', 'statement_id')) {
                $table->foreignId('statement_id')->nullable()->after('request_id')->constrained('billing_statements')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('agency_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('statement_id');
        });
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn('terminated_at');
        });
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn('pending_note');
        });
        Schema::table('agency_users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
        Schema::dropIfExists('worker_rate_requests');
        Schema::dropIfExists('manpower_proposals');
        Schema::dropIfExists('billing_statements');
        Schema::dropIfExists('attendance_adjustments');
        Schema::dropIfExists('agency_attendances');
    }
};
