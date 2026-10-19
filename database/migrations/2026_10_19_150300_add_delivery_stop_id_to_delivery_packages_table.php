<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_packages', function (Blueprint $table) {
            // Which stop (client drop-off) this package belongs to within the load.
            $table->foreignId('delivery_stop_id')->nullable()->after('warehouse_package_id')
                ->constrained('delivery_stops')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_stop_id');
        });
    }
};
