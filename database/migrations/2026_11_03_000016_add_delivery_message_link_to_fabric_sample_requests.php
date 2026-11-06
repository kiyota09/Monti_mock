<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fabric_sample_requests', 'delivery_message_id')) {
            Schema::table('fabric_sample_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('delivery_message_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fabric_sample_requests', 'delivery_message_id')) {
            Schema::table('fabric_sample_requests', function (Blueprint $table) {
                $table->dropIndex(['delivery_message_id']);
                $table->dropColumn('delivery_message_id');
            });
        }
    }
};
