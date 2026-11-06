<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->decimal('load_capacity_kg', 10, 2)->nullable()->after('power');
        });

        Schema::create('dyeing_batches', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('manufacturing_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained('machines')->restrictOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('color', 120);
            $table->decimal('load_capacity_kg', 10, 2);
            $table->decimal('total_weight_kg', 10, 2)->default(0);
            $table->string('status', 32)->default('queued'); // queued|in_progress|completed|remake_requested
            $table->timestamps();
            $table->index(['sales_order_id', 'status']);
        });

        Schema::create('dyeing_batch_fabrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dyeing_batch_id')->constrained('dyeing_batches')->cascadeOnDelete();
            $table->foreignId('fabric_id')->unique()->constrained('fabrics')->restrictOnDelete();
            $table->boolean('requires_bleaching')->default(false);
            $table->string('current_stage', 32)->default('pre_treatment'); // bleaching|pre_treatment|color|post_treatment|completed
            $table->string('status', 32)->default('active'); // active|awaiting_review|completed|remake_requested|replaced
            $table->foreignId('replacement_fabric_id')->nullable()->constrained('fabrics')->nullOnDelete();
            $table->timestamps();
            $table->index(['current_stage', 'status']);
        });

        Schema::create('dyeing_stage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dyeing_batch_fabric_id')->constrained('dyeing_batch_fabrics')->cascadeOnDelete();
            $table->string('stage', 32);
            $table->unsignedInteger('attempt')->default(1);
            $table->foreignId('operator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('dye_job_id')->nullable()->constrained('dye_jobs')->nullOnDelete();
            $table->foreignId('bleaching_job_id')->nullable()->constrained('bleaching_jobs')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('review_status', 32)->default('awaiting_review'); // awaiting_review|approved|redo|sent_to_bleaching|rejected
            $table->text('operator_remarks')->nullable();
            $table->text('supervisor_remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['dyeing_batch_fabric_id', 'stage', 'attempt'], 'dyeing_stage_attempt_idx');
            $table->index(['review_status', 'stage']);
        });

        Schema::create('dyeing_remake_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('dyeing_batch_id')->constrained('dyeing_batches')->restrictOnDelete();
            $table->foreignId('manufacturing_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('machine_ids');
            $table->decimal('requested_weight_kg', 10, 2);
            $table->string('status', 32)->default('knitting'); // knitting|awaiting_inspection|ready_for_dyeing|completed
            $table->timestamps();
            $table->index(['sales_order_id', 'status']);
        });

        Schema::create('dyeing_remake_request_fabrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dyeing_remake_request_id')->constrained('dyeing_remake_requests')->cascadeOnDelete();
            $table->foreignId('original_fabric_id')->constrained('fabrics')->restrictOnDelete();
            $table->foreignId('replacement_fabric_id')->nullable()->constrained('fabrics')->nullOnDelete();
            $table->timestamps();
            $table->unique(['dyeing_remake_request_id', 'original_fabric_id'], 'dyeing_remake_original_unique');
        });

        Schema::table('man_task_assignments', function (Blueprint $table) {
            $table->foreignId('dyeing_remake_request_id')->nullable()->after('assigned_weight')
                ->constrained('dyeing_remake_requests')->nullOnDelete();
        });
        Schema::table('fabrics', function (Blueprint $table) {
            $table->foreignId('dyeing_remake_request_id')->nullable()->after('machine_id')
                ->constrained('dyeing_remake_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fabrics', fn (Blueprint $table) => $table->dropConstrainedForeignId('dyeing_remake_request_id'));
        Schema::dropIfExists('dyeing_remake_request_fabrics');
        Schema::table('man_task_assignments', fn (Blueprint $table) => $table->dropConstrainedForeignId('dyeing_remake_request_id'));
        Schema::dropIfExists('dyeing_remake_requests');
        Schema::dropIfExists('dyeing_stage_records');
        Schema::dropIfExists('dyeing_batch_fabrics');
        Schema::dropIfExists('dyeing_batches');
        Schema::table('machines', fn (Blueprint $table) => $table->dropColumn('load_capacity_kg'));
    }
};
