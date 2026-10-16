<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->softDeletes(); // Adds the nullable `deleted_at` timestamp column
        });
    }

    public function down(): void
    {
        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->dropSoftDeletes(); // Removes `deleted_at` on rollback
        });
    }
};
