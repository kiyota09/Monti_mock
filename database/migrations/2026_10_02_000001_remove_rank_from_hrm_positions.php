<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('hrm_positions', 'rank')) {
            Schema::table('hrm_positions', function (Blueprint $table) {
                $table->dropColumn('rank');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('hrm_positions', 'rank')) {
            Schema::table('hrm_positions', function (Blueprint $table) {
                $table->unsignedTinyInteger('rank')->nullable(); // 1-10 (removed)
            });
        }
    }
};
