<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexExists = collect(Schema::getIndexes('payrolls'))
            ->contains(fn (array $index) => $index['name'] === 'payrolls_employee_period_unique');

        if ($indexExists) {
            return;
        }

        Schema::table('payrolls', function (Blueprint $table) {
            $table->unique(['employee_id', 'period'], 'payrolls_employee_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropUnique('payrolls_employee_period_unique');
        });
    }
};
