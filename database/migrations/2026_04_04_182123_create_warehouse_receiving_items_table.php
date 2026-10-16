<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('warehouse_receiving_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_id')->constrained('warehouse_receivings')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained();
            $table->decimal('expected_qty', 12, 2);
            $table->decimal('received_qty', 12, 2)->default(0);
            $table->decimal('rejected_qty', 12, 2)->default(0);
            $table->enum('status', ['pending', 'accepted', 'rejected', 'partial'])->default('pending');
            $table->text('reject_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse_receiving_items');
    }
};