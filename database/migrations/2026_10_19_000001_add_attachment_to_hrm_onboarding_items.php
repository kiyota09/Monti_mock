<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carry the HR-uploaded reference file onto the per-hire snapshot so
     * the applicant portal can show it (templates stay blueprints).
     */
    public function up(): void
    {
        Schema::table('hrm_onboarding_items', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('original_name');
            $table->string('attachment_name')->nullable()->after('attachment_path');
        });
    }

    public function down(): void
    {
        Schema::table('hrm_onboarding_items', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name']);
        });
    }
};
