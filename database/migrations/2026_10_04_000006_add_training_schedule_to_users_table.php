<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'training_start_date')) {
                $table->date('training_start_date')->nullable()->after('join_date');
            }
            if (! Schema::hasColumn('users', 'training_end_date')) {
                $table->date('training_end_date')->nullable()->after('training_start_date');
            }
            if (! Schema::hasColumn('users', 'training_duration_weeks')) {
                $table->unsignedInteger('training_duration_weeks')->nullable()->after('training_end_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['training_duration_weeks', 'training_end_date', 'training_start_date'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
