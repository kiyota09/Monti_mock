<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_job_postings', function (Blueprint $table) {
            $table->boolean('training_required')->default(false)->after('require_final_interview');
        });
    }

    public function down(): void
    {
        Schema::table('hrm_job_postings', function (Blueprint $table) {
            $table->dropColumn('training_required');
        });
    }
};
