<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'worker_id')) {
<<<<<<< HEAD:database/migrations/2026_10_19_000003_add_worker_id_to_users_table.php
            $hasWorkerForeignKey = collect(Schema::getForeignKeys('users'))->contains(
                fn (array $foreignKey) => ($foreignKey['columns'] ?? []) === ['worker_id']
                    && ($foreignKey['foreign_table'] ?? null) === 'workers'
            );

            if (! $hasWorkerForeignKey) {
                Schema::table('users', function (Blueprint $table) {
                    $table->foreign('worker_id')->references('id')->on('workers')->nullOnDelete();
                });
            }

=======
>>>>>>> 4a5038e28b6a55ea65129e3176825edb8849a073:database/migrations/2026_10_19_000004_add_worker_id_to_users_table.php
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
