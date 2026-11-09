<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_adjustments', function (Blueprint $table) {
            $table->decimal('allowance', 12, 2)->default(0)->after('new_salary');
        });
    }

    public function down(): void
    {
        Schema::table('salary_adjustments', function (Blueprint $table) {
            $table->dropColumn('allowance');
        });
    }
};
