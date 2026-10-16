<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // This table will hold the queue of orders from ECO to SCM/INV/MAN
        Schema::create('order_queues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id'); // from existing purchase_orders table
            $table->enum('stage', ['eco_approved', 'scm_received', 'inv_check', 'man_production', 'completed'])->default('eco_approved');
            $table->timestamp('scm_received_at')->nullable();
            $table->timestamp('inv_checked_at')->nullable();
            $table->timestamp('man_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->unique('purchase_order_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_queues');
    }
};
