<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('warehouse_receivings', function (Blueprint $table) {
            $table->id();
            $table->string('receiving_number')->unique();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('scm_purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('received_at');
            $table->foreignId('received_by')->constrained('users');
            $table->enum('status', ['pending', 'partial', 'completed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse_receivings');
    }
};