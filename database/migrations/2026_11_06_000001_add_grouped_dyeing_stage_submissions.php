<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dyeing_stage_records', function (Blueprint $table) {
            $table->string('submission_code', 40)->nullable()->index()->after('code');
            $table->json('materials_used')->nullable()->after('operator_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('dyeing_stage_records', function (Blueprint $table) {
            $table->dropIndex(['submission_code']);
            $table->dropColumn(['submission_code', 'materials_used']);
        });
    }
};
