<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-stage flag: when on, every deal sitting in this column shows an
     * "Upload files/images" button on its card. Useful for stages that collect
     * documents (e.g. requirement submissions, POs, signed forms).
     */
    public function up(): void
    {
        Schema::table('crm_stages', function (Blueprint $table) {
            $table->boolean('allows_uploads')->default(false)->after('is_lost');
        });
    }

    public function down(): void
    {
        Schema::table('crm_stages', function (Blueprint $table) {
            $table->dropColumn('allows_uploads');
        });
    }
};
