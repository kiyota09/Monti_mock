<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'work_mode')) {
                $table->string('work_mode', 60)->nullable();
            }
            if (! Schema::hasColumn('users', 'salary_type')) {
                $table->string('salary_type', 60)->nullable();
            }
            if (! Schema::hasColumn('users', 'pay_frequency')) {
                $table->string('pay_frequency', 60)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['work_mode', 'salary_type', 'pay_frequency'],
                fn (string $column) => Schema::hasColumn('users', $column),
            ));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
