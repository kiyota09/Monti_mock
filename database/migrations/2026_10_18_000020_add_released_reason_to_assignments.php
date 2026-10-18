<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manpower_assignments', function (Blueprint $table) {
            $table->text('released_reason')->nullable()->after('released_at');
        });
    }

    public function down(): void
    {
        Schema::table('manpower_assignments', function (Blueprint $table) {
            $table->dropColumn('released_reason');
        });
    }
};
