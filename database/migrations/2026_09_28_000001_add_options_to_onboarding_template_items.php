<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_onboarding_template_items', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_onboarding_template_items', 'options')) {
                $table->json('options')->nullable()->after('input_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hrm_onboarding_template_items', function (Blueprint $table) {
            if (Schema::hasColumn('hrm_onboarding_template_items', 'options')) {
                $table->dropColumn('options');
            }
        });
    }
};
