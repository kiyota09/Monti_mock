<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IT module (ITIL 4-aligned, textile-manufacturing fit):
 *  - Service desk: incidents + service requests with SLA tracking
 *  - IT asset management: hardware / software / network / peripheral register
 *  - Systems & network monitoring with status-check log
 *  - Knowledge base for office + plant self-help
 *  - Change enablement (lightweight CAB approvals + maintenance windows)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Service desk tickets ──────────────────────────────────────────
        Schema::create('it_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no')->unique(); // e.g. INC-2026-0001 / SRV-2026-0001
            $table->string('title');
            $table->text('description');
            $table->enum('category', ['incident', 'service_request'])->default('incident');
            $table->string('system_area', 50)->default('erp'); // erp, network, hardware, software, plant_ot, peripheral, access
            $table->enum('priority', ['P1', 'P2', 'P3', 'P4'])->default('P3');
            $table->enum('status', ['open', 'assigned', 'in_progress', 'pending', 'resolved', 'closed', 'reopened'])->default('open');
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name')->nullable(); // free text for plant-floor callers without ERP accounts
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('location')->nullable(); // e.g. "Dyeing floor — Lab terminal 2"
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index('assignee_id');
        });

        Schema::create('it_ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('it_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false); // internal notes vs requester-visible updates
            $table->timestamps();
        });

        // ── IT asset register ─────────────────────────────────────────────
        Schema::create('it_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique(); // e.g. AST-2026-0001
            $table->string('name');
            $table->enum('category', ['hardware', 'software', 'network', 'peripheral'])->default('hardware');
            $table->string('type', 100); // laptop, server, switch, barcode_scanner, printer, license, ...
            $table->string('serial_number')->nullable();
            $table->text('specs')->nullable();
            $table->string('location')->nullable();
            $table->enum('status', ['available', 'in_use', 'maintenance', 'retired'])->default('available');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_end')->nullable();
            $table->string('vendor')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'category']);
        });

        Schema::create('it_asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('it_assets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
        });

        // ── Systems & network monitoring ──────────────────────────────────
        Schema::create('it_systems', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "MontiERP production", "Plant-floor WiFi"
            $table->enum('system_type', ['server', 'network', 'application', 'power', 'security', 'plant_ot', 'endpoint'])->default('application');
            $table->string('location')->nullable();
            $table->string('host')->nullable(); // IP / hostname
            $table->enum('status', ['operational', 'degraded', 'down', 'maintenance'])->default('operational');
            $table->timestamp('last_checked_at')->nullable();
            $table->foreignId('last_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('it_system_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->constrained('it_systems')->cascadeOnDelete();
            $table->enum('status', ['operational', 'degraded', 'down', 'maintenance']);
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // ── Knowledge base ────────────────────────────────────────────────
        Schema::create('it_knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category', 100)->default('general'); // erp, network, hardware, security, plant_ot, general
            $table->text('body');
            $table->enum('audience', ['all', 'office', 'plant', 'it_staff'])->default('all');
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('views')->default(0);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ── Change enablement ─────────────────────────────────────────────
        Schema::create('it_changes', function (Blueprint $table) {
            $table->id();
            $table->string('change_no')->unique(); // e.g. CHG-2026-0001
            $table->string('title');
            $table->text('description');
            $table->enum('change_type', ['standard', 'normal', 'emergency'])->default('normal');
            $table->enum('risk', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'in_progress', 'completed', 'rejected', 'rolled_back'])->default('draft');
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_end')->nullable();
            $table->foreignId('implemented_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rollback_plan')->nullable();
            $table->text('completion_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_changes');
        Schema::dropIfExists('it_knowledge_articles');
        Schema::dropIfExists('it_system_checks');
        Schema::dropIfExists('it_systems');
        Schema::dropIfExists('it_asset_assignments');
        Schema::dropIfExists('it_assets');
        Schema::dropIfExists('it_ticket_comments');
        Schema::dropIfExists('it_tickets');
    }
};
