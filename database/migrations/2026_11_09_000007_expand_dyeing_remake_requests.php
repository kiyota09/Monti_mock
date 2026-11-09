<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dyeing_remake_requests', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->change();
            $table->json('machine_ids')->nullable()->change();
            $table->text('reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('dyeing_remake_requests', function (Blueprint $table) {
            $table->dropColumn('reason');
            $table->foreignId('assigned_to')->nullable(false)->change();
            $table->json('machine_ids')->nullable(false)->change();
        });
    }
};
