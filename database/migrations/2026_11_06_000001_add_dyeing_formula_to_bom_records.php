<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bom_records', 'dyeing_formula')) {
            Schema::table('bom_records', function (Blueprint $table) {
                $table->json('dyeing_formula')->nullable()->after('materials');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bom_records', 'dyeing_formula')) {
            Schema::table('bom_records', function (Blueprint $table) {
                $table->dropColumn('dyeing_formula');
            });
        }
    }
};
