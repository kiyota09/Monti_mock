<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Applicant portal credentials (mirrors client/supplier guards).
     * Nullable password: legacy rows (filed via public /apply form)
     * claim their account by setting a password on first login attempt
     * via the registration page (same email).
     */
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->rememberToken()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn(['password', 'remember_token']);
        });
    }
};
