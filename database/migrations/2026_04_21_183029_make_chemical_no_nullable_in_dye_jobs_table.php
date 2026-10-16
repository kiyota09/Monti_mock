<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * chemical_no is now populated from the primary inventory lot's control_number.
     * Making it nullable preserves any existing rows that may have been created
     * before this migration.
     */
    public function up(): void
    {
        Schema::table('dye_jobs', function (Blueprint $table) {
            $table->string('chemical_no')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dye_jobs', function (Blueprint $table) {
            $table->string('chemical_no')->nullable(false)->change();
        });
    }
};