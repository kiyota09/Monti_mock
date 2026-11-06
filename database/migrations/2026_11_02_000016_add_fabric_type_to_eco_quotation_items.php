<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eco_quotation_items', function (Blueprint $table) {
            $table->foreignId('fabric_type_id')
                ->nullable()
                ->after('fabric')
                ->constrained('materials')
                ->nullOnDelete();
            $table->string('fabric_type')->nullable()->after('fabric_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('eco_quotation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fabric_type_id');
            $table->dropColumn('fabric_type');
        });
    }
};
