<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo-style CRM pipeline restructure (additive, BC-safe).
 *
 * - crm_stages: customizable kanban columns (sequence, folded, won flag, default probability).
 * - crm_opportunities: canonical stage_id FK + contact link + priority + notes + marketing fields.
 *   Legacy string `stage` column is kept and synced for BC.
 * - crm_activities: reminder/todo types + explicit status (planned|done|cancelled) + assigned_to.
 *   Legacy done_at is kept and synced.
 * - crm_stage_automation_rules: placeholder for future per-stage automations (not executed yet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sequence')->default(0);
            $table->boolean('is_folded')->default(false);
            $table->boolean('is_won')->default(false);
            $table->unsignedTinyInteger('default_probability')->default(10);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('sequence');
        });

        Schema::create('crm_stage_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('crm_stages')->cascadeOnDelete();
            $table->string('trigger'); // e.g. on_enter, on_exit, on_overdue (future)
            $table->string('action');  // e.g. create_activity, notify, set_field (future)
            $table->json('payload')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['stage_id', 'is_active']);
        });

        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->foreignId('stage_id')->nullable()->after('stage')->constrained('crm_stages')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->after('lead_id')->constrained('crm_contacts')->nullOnDelete();
            $table->unsignedTinyInteger('priority')->default(0)->after('probability');
            $table->text('internal_notes')->nullable()->after('lost_reason');
            $table->string('source')->nullable()->after('internal_notes');
            $table->string('medium')->nullable()->after('source');
            $table->string('campaign')->nullable()->after('medium');
            $table->string('referred_by')->nullable()->after('campaign');
            $table->string('email')->nullable()->after('referred_by');
            $table->string('phone', 32)->nullable()->after('email');

            $table->index(['stage_id', 'owner_id']);
            $table->index('contact_id');
        });

        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->string('organization')->nullable()->after('name');
        });

        Schema::table('crm_activities', function (Blueprint $table) {
            // New canonical fields; legacy subject/body/due_at/done_at/owner_id are kept + synced.
            $table->string('summary')->nullable()->after('subject');
            $table->dateTime('due_date')->nullable()->after('due_at');
            $table->foreignId('assigned_to')->nullable()->after('owner_id')->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable()->after('body');
            $table->string('status', 16)->default('planned')->after('done_at'); // planned|done|cancelled

            $table->index(['opportunity_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        // Widen legacy enums where MySQL allows it (kept additive for BC).
        try {
            DB::statement("ALTER TABLE crm_activities MODIFY COLUMN type VARCHAR(16) NOT NULL DEFAULT 'note'");
        } catch (\Throwable $e) {
            // SQLite / other drivers: skip, validation handles new types.
        }

        // Backfill new status column from legacy done_at.
        try {
            DB::table('crm_activities')->whereNotNull('done_at')->update(['status' => 'done']);
            DB::table('crm_activities')->whereNull('done_at')->update(['status' => 'planned']);
        } catch (\Throwable $e) {
            // Fresh installs have no rows — safe to ignore.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_stage_automation_rules');
        Schema::dropIfExists('crm_stages');

        Schema::table('crm_activities', function (Blueprint $table) {
            $table->dropIndex(['opportunity_id', 'status']);
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['summary', 'due_date', 'assigned_to', 'notes', 'status']);
        });

        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropColumn('organization');
        });

        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->dropIndex(['stage_id', 'owner_id']);
            $table->dropIndex(['contact_id']);
            $table->dropForeign(['stage_id', 'contact_id']);
            $table->dropColumn([
                'stage_id', 'contact_id', 'priority', 'internal_notes',
                'source', 'medium', 'campaign', 'referred_by', 'email', 'phone',
            ]);
        });
    }
};
