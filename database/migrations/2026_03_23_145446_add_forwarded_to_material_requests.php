<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('material_requests', function (Blueprint $table) {
            // Add forwarded status to enum (if not already)
            DB::statement("ALTER TABLE material_requests MODIFY COLUMN status ENUM('pending','forwarded','rfq_sent','po_created','fulfilled','cancelled') NOT NULL DEFAULT 'pending'");
            $table->timestamp('forwarded_at')->nullable();
            $table->unsignedBigInteger('forwarded_by')->nullable();
            $table->foreign('forwarded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropForeign(['forwarded_by']);
            $table->dropColumn(['forwarded_at', 'forwarded_by']);
            DB::statement("ALTER TABLE material_requests MODIFY COLUMN status ENUM('pending','rfq_sent','po_created','fulfilled','cancelled') NOT NULL DEFAULT 'pending'");
        });
    }
};
