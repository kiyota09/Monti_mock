<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manpower_requests', function (Blueprint $table) {
            $table->foreignId('employment_type_id')
                ->nullable()
                ->after('shift')
                ->constrained('hrm_employment_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('manpower_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employment_type_id');
        });
    }
};
