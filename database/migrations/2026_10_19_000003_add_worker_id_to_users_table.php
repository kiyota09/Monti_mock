<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'worker_id')) {
            $hasWorkerForeignKey = collect(Schema::getForeignKeys('users'))->contains(
                fn (array $foreignKey) => ($foreignKey['columns'] ?? []) === ['worker_id']
                    && ($foreignKey['foreign_table'] ?? null) === 'workers'
            );

            if (! $hasWorkerForeignKey) {
                Schema::table('users', function (Blueprint $table) {
                    $table->foreign('worker_id')->references('id')->on('workers')->nullOnDelete();
                });
            }

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('worker_id')->nullable()->after('employee_id')
                ->constrained('workers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('worker_id');
        });
    }
};
