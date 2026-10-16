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
        Schema::table('applicants', function (Blueprint $table) {
            $table->enum('notice_period', ['immediate', '15_days', '30_days', '60_days'])
                ->default('immediate')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->enum('notice_period', ['immediate', '15_days', '30_days'])
                ->default('immediate')
                ->change();
        });
    }
};
