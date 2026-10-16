<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('manufacturing_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('control_number')->unique(); // same as warehouse control number or new generated
            $table->foreignId('material_id')->constrained('materials')->onDelete('cascade');
            $table->foreignId('warehouse_stock_item_id')->nullable()->constrained('warehouse_stock_items')->onDelete('set null');
            $table->decimal('initial_quantity', 12, 2);
            $table->decimal('remaining_quantity', 12, 2);
            $table->string('unit'); // Kg, Pcs, etc.
            $table->string('category'); // Yarn, Dye, Supplies, Packaging
            $table->enum('status', ['available', 'partial', 'depleted'])->default('available');
            
            // Container breakdown fields (optional)
            $table->integer('total_units')->nullable(); // total rolls/boxes
            $table->integer('used_units')->nullable()->default(0);
            $table->string('unit_type')->nullable(); // 'roll', 'box'
            $table->decimal('unit_weight', 10, 2)->nullable(); // kg per unit (for yarn)
            
            $table->string('department'); // knitting, dyeing, maintenance, packaging
            $table->timestamp('received_at')->useCurrent();
            $table->foreignId('received_from')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('manufacturing_inventory_items');
    }
};