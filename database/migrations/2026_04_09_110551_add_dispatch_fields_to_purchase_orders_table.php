<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
        $table->string('attachment_path')->nullable()->after('notes');
        $table->string('control_number')->nullable()->after('attachment_path');
        $table->string('yarn')->nullable()->after('control_number');
        // Ensure subtotal exists or is nullable
        $table->decimal('subtotal', 15, 2)->default(0)->change(); 
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            //
        });
    }
};
