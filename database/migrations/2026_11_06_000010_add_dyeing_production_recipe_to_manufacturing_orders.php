<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('manufacturing_orders', 'dyeing_production_recipe')) {
            Schema::table('manufacturing_orders', function (Blueprint $table) {
                $table->json('dyeing_production_recipe')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('manufacturing_orders', 'dyeing_production_recipe')) {
            Schema::table('manufacturing_orders', function (Blueprint $table) {
                $table->dropColumn('dyeing_production_recipe');
            });
        }
    }
};
