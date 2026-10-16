<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            // If it's an ENUM, we must redefine all allowed values
            $table->enum('notice_period', ['immediate', '15_days', '30_days', '60_days'])
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->enum('notice_period', ['immediate', '15_days', '30_days'])
                ->change();
        });
    }
};
