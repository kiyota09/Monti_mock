<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_sample_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('fabric_sample_requests', 'delivery_courier')) {
                $table->string('delivery_courier', 120)->nullable();
            }
            if (! Schema::hasColumn('fabric_sample_requests', 'delivery_tracking_reference')) {
                $table->string('delivery_tracking_reference', 160)->nullable();
            }
            if (! Schema::hasColumn('fabric_sample_requests', 'delivery_notes')) {
                $table->text('delivery_notes')->nullable();
            }
            if (! Schema::hasColumn('fabric_sample_requests', 'delivery_sent_at')) {
                $table->timestamp('delivery_sent_at')->nullable();
            }
            if (! Schema::hasColumn('fabric_sample_requests', 'client_noted_at')) {
                $table->timestamp('client_noted_at')->nullable();
            }
            if (! Schema::hasColumn('fabric_sample_requests', 'client_received_at')) {
                $table->timestamp('client_received_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fabric_sample_requests', function (Blueprint $table) {
            $columns = ['delivery_courier', 'delivery_tracking_reference', 'delivery_notes', 'delivery_sent_at', 'client_noted_at', 'client_received_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('fabric_sample_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
