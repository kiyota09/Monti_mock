<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_sample_requests', function (Blueprint $table) {
            $table->string('texture', 16)->nullable()->after('fabric_name');
            $table->unsignedInteger('gsm')->nullable()->after('texture');
        });
    }

    public function down(): void
    {
        Schema::table('fabric_sample_requests', function (Blueprint $table) {
            $table->dropColumn(['texture', 'gsm']);
        });
    }
};
