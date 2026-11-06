<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finishing_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturing_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->string('extraction_method', 24);
            $table->foreignId('extraction_assigned_to')->constrained('users')->restrictOnDelete();
            $table->string('drying_method', 24)->nullable();
            $table->foreignId('drying_assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('extraction');
            $table->foreignId('quality_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('quality_checked_at')->nullable();
            $table->text('quality_remarks')->nullable();
            $table->timestamps();
            $table->index(['status', 'sales_order_id']);
        });

        Schema::create('finishing_work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finishing_work_order_id')->constrained('finishing_work_orders')->cascadeOnDelete();
            $table->foreignId('fabric_id')->unique()->constrained('fabrics')->restrictOnDelete();
            $table->string('status', 32)->default('extraction');
            $table->timestamps();
            $table->index(['finishing_work_order_id', 'status']);
        });

        Schema::create('finishing_work_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finishing_work_item_id')->constrained('finishing_work_items')->cascadeOnDelete();
            $table->string('stage', 24);
            $table->unsignedInteger('attempt')->default(1);
            $table->foreignId('operator_id')->constrained('users')->restrictOnDelete();
            $table->decimal('temperature_c', 6, 2)->nullable();
            $table->decimal('gsm', 8, 2)->nullable();
            $table->decimal('shrinkage_percent', 7, 3)->nullable();
            $table->text('chemicals_used')->nullable();
            $table->text('remarks')->nullable();
            $table->string('result', 24)->default('submitted');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['finishing_work_item_id', 'stage', 'attempt'], 'finishing_work_record_attempt_idx');
        });

        Schema::create('finishing_package_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finishing_work_order_id')->constrained('finishing_work_orders')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('group_name', 120)->nullable();
            $table->foreignId('packed_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('packed');
            $table->foreignId('warehouse_package_id')->nullable()->constrained('warehouse_packages')->nullOnDelete();
            $table->timestamp('sent_to_logistics_at')->nullable();
            $table->timestamps();
            $table->index(['finishing_work_order_id', 'status']);
        });

        Schema::create('finishing_package_group_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finishing_package_group_id')->constrained('finishing_package_groups')->cascadeOnDelete();
            $table->foreignId('finishing_work_item_id')->unique()->constrained('finishing_work_items')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finishing_package_group_items');
        Schema::dropIfExists('finishing_package_groups');
        Schema::dropIfExists('finishing_work_records');
        Schema::dropIfExists('finishing_work_items');
        Schema::dropIfExists('finishing_work_orders');
    }
};
