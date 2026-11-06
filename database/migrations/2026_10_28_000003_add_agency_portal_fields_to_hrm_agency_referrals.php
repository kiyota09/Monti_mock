<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_agency_referrals', function (Blueprint $table) {
            $table->string('agency_response', 40)->nullable()->after('sent_at');
            $table->text('monti_approved_feedback')->nullable()->after('agency_instructions');
        });

        Schema::table('hrm_agency_referral_histories', function (Blueprint $table) {
            $table->foreignId('agency_user_id')->nullable()->after('actor_id')
                ->constrained('agency_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hrm_agency_referral_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agency_user_id');
        });

        Schema::table('hrm_agency_referrals', function (Blueprint $table) {
            $table->dropColumn(['agency_response', 'monti_approved_feedback']);
        });
    }
};
