<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conductor_reports', function (Blueprint $table) {
            $table->enum('category', ['delay', 'accident', 'breakdown', 'customer', 'safety', 'other'])
                ->default('other')->after('report_text');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])
                ->default('medium')->after('category');
            $table->string('photo_path')->nullable()->after('severity');
            $table->enum('status', ['open', 'acknowledged', 'resolved'])
                ->default('open')->after('photo_path');
            $table->timestamp('reported_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('conductor_reports', function (Blueprint $table) {
            $table->dropColumn(['category', 'severity', 'photo_path', 'status', 'reported_at']);
        });
    }
};
