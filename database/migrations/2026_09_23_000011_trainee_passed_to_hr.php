<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trainee → HR handoff flag referenced by Hrm\TraineeController@pass.
     */
    public function up(): void
    {
        Schema::table('trainee_grades', function (Blueprint $table) {
            $table->boolean('passed_to_hr')->default(false)->after('total_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('trainee_grades', function (Blueprint $table) {
            $table->dropColumn('passed_to_hr');
        });
    }
};
