<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Industry-standard CRM objects for Monti Textile (B2B mill):
     * - crm_contacts: people at prospect/client companies (decision makers)
     * - crm_opportunities + histories: staged deal pipeline with audit trail
     * - crm_activities: unified calls/meetings/notes/tasks timeline
     * - crm_cases: post-sale complaints & claims with severity + SLA states
     * - crm_campaigns + links: marketing campaigns with lead attribution
     * - crm_leads: BANT-lite qualification + next-step tracking
     *
     * Backfills one primary contact per existing client/lead so the
     * directory is populated from day one. All new tables are additive —
     * no existing table is altered except additive lead columns.
     */
    public function up(): void
    {
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->cascadeOnDelete();
            $table->string('name');
            $table->string('title')->nullable(); // purchasing manager, owner, …
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_decision_maker')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'is_primary']);
            $table->index('lead_id');
        });

        Schema::create('crm_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->string('title');
            $table->enum('stage', ['qualification', 'sampling', 'quotation', 'negotiation', 'won', 'lost'])->default('qualification');
            $table->decimal('value', 15, 2)->default(0);
            $table->unsignedTinyInteger('probability')->default(10);
            $table->date('expected_close')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lost_reason')->nullable();
            $table->timestamps();

            $table->index(['stage', 'owner_id']);
            $table->index('client_id');
        });

        Schema::create('crm_opportunity_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('crm_opportunities')->cascadeOnDelete();
            $table->string('from_stage')->nullable();
            $table->string('to_stage');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('crm_opportunities')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->cascadeOnDelete();
            $table->enum('type', ['call', 'meeting', 'note', 'task', 'email'])->default('note');
            $table->string('subject');
            $table->text('body')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'done_at']);
            $table->index(['owner_id', 'done_at']);
        });

        Schema::create('crm_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('feedback_id')->nullable()->constrained('crm_feedback')->nullOnDelete();
            $table->string('subject');
            $table->enum('category', ['defect', 'shortage', 'delay', 'billing', 'other'])->default('other');
            $table->enum('severity', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->index('client_id');
        });

        Schema::create('crm_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel')->default('social'); // social, email, tradeshow, referral
            $table->string('audience')->nullable();
            $table->decimal('cost', 15, 2)->default(0);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->enum('status', ['draft', 'active', 'completed'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('crm_campaign_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('crm_campaigns')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['campaign_id', 'lead_id']);
        });

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->string('budget')->nullable()->after('estimated_value');
            $table->string('authority')->nullable()->after('budget');
            $table->text('need_summary')->nullable()->after('authority');
            $table->string('timeline')->nullable()->after('need_summary');
            $table->string('next_step')->nullable()->after('timeline');
            $table->date('next_step_due')->nullable()->after('next_step');
        });

        // Backfill primary contacts from existing companies + prospects.
        $clients = DB::table('clients')->select('id', 'contact_person', 'email', 'phone')->get();
        foreach ($clients as $c) {
            if (! $c->contact_person && ! $c->email) {
                continue;
            }
            DB::table('crm_contacts')->insert([
                'client_id' => $c->id,
                'name' => $c->contact_person ?: $c->email,
                'email' => $c->email,
                'phone' => $c->phone,
                'is_decision_maker' => true,
                'is_primary' => true,
                'notes' => 'Backfilled from company record.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $leads = DB::table('crm_leads')->select('id', 'contact_person', 'email', 'phone')->get();
        foreach ($leads as $l) {
            if (! $l->contact_person && ! $l->email) {
                continue;
            }
            DB::table('crm_contacts')->insert([
                'lead_id' => $l->id,
                'name' => $l->contact_person ?: $l->email,
                'email' => $l->email,
                'phone' => $l->phone,
                'is_decision_maker' => true,
                'is_primary' => true,
                'notes' => 'Backfilled from lead record.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_campaign_leads');
        Schema::dropIfExists('crm_campaigns');
        Schema::dropIfExists('crm_cases');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_opportunity_histories');
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_contacts');

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropColumn(['budget', 'authority', 'need_summary', 'timeline', 'next_step', 'next_step_due']);
        });
    }
};
