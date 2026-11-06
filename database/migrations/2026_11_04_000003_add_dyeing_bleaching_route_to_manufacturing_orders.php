<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacturing_orders', function (Blueprint $table) {
            $table->boolean('dyeing_bleaching_required')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('manufacturing_orders', function (Blueprint $table) {
            $table->dropColumn('dyeing_bleaching_required');
        });
    }
};
