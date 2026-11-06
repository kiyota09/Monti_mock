<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_sample_requests', function (Blueprint $table) {
            $table->string('color_hex', 7)->nullable()->after('gsm');
            $table->string('color_image_path')->nullable()->after('color_hex');
        });
    }

    public function down(): void
    {
        Schema::table('fabric_sample_requests', function (Blueprint $table) {
            $table->dropColumn(['color_hex', 'color_image_path']);
        });
    }
};
