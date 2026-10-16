<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add new fields to pricing_tiers table
        Schema::table('pricing_tiers', function (Blueprint $table) {
            if (! Schema::hasColumn('pricing_tiers', 'type')) {
                $table->enum('type', ['volume', 'holiday', 'client_specific'])->default('volume');
            }
            if (! Schema::hasColumn('pricing_tiers', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable();
                $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            }
            if (! Schema::hasColumn('pricing_tiers', 'holiday_date')) {
                $table->date('holiday_date')->nullable();
            }
            if (! Schema::hasColumn('pricing_tiers', 'start_date')) {
                $table->date('start_date')->nullable();
            }
            if (! Schema::hasColumn('pricing_tiers', 'end_date')) {
                $table->date('end_date')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('pricing_tiers', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn(['type', 'client_id', 'holiday_date', 'start_date', 'end_date']);
        });
    }
};
