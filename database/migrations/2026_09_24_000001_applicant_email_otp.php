<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Applicant email-OTP verification (Auth-1 style OTP flow).
     * Registration issues a 6-digit code; the applicant verifies it on
     * Auth/VerifyOtp before the portal session is created.
     */
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('email_otp')->nullable()->after('email_verified_at');
            $table->timestamp('email_otp_expires_at')->nullable()->after('email_otp');
            $table->unsignedInteger('email_otp_attempts')->default(0)->after('email_otp_expires_at');
            $table->timestamp('email_otp_locked_until')->nullable()->after('email_otp_attempts');
            $table->unsignedInteger('email_otp_lock_count')->default(0)->after('email_otp_locked_until');
            $table->timestamp('email_otp_sent_at')->nullable()->after('email_otp_lock_count');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn([
                'email_otp',
                'email_otp_expires_at',
                'email_otp_attempts',
                'email_otp_locked_until',
                'email_otp_lock_count',
                'email_otp_sent_at',
            ]);
        });
    }
};
