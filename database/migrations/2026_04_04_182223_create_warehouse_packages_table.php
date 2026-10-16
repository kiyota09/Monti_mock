<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('warehouse_packages', function (Blueprint $table) {
            $table->id();
            $table->string('package_number')->unique();
            $table->foreignId('manufacturing_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity');
            $table->enum('status', ['pending', 'pushed_to_logistics', 'delivered'])->default('pending');
            $table->timestamp('pushed_at')->nullable();
            $table->foreignId('pushed_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse_packages');
    }
};