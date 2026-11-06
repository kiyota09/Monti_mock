<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fabric_sample_requests', 'pantone_colors')) {
            Schema::table('fabric_sample_requests', function (Blueprint $table) {
                $table->json('pantone_colors')->nullable()->after('pantone_formula');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fabric_sample_requests', 'pantone_colors')) {
            Schema::table('fabric_sample_requests', function (Blueprint $table) {
                $table->dropColumn('pantone_colors');
            });
        }
    }
};
