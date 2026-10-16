<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Removes the roll_no column from fabrics.
     * The auto-generated fabric `code` (e.g. FABRIC-2026-00001) serves as
     * the unique identifier going forward.
     */
    public function up(): void
    {
        Schema::table('fabrics', function (Blueprint $table) {
            $table->dropColumn('roll_no');
        });
    }

    /**
     * Reverse the migrations.
     * Restores roll_no as nullable so existing rows are not broken on rollback.
     */
    public function down(): void
    {
        Schema::table('fabrics', function (Blueprint $table) {
            $table->string('roll_no')->nullable()->after('yarn_type');
        });
    }
};