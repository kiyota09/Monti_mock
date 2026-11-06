<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_sample_requests', function (Blueprint $table) {
            $table->string('delivery_group_code', 40)->nullable()->index()->after('delivery_tracking_reference');
        });
    }

    public function down(): void
    {
        Schema::table('fabric_sample_requests', function (Blueprint $table) {
            $table->dropIndex(['delivery_group_code']);
            $table->dropColumn('delivery_group_code');
        });
    }
};
