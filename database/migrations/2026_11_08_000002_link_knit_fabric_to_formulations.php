<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_sample_formulations', function (Blueprint $table) {
            $table->foreignId('knitting_sample_request_id')
                ->nullable()
                ->after('fabric_sample_request_id')
                ->constrained('knitting_sample_requests')
                ->nullOnDelete();
            $table->unsignedInteger('color_index')->nullable()->after('knitting_sample_request_id');
            $table->unique('knitting_sample_request_id', 'fsf_knit_request_unique');
        });
    }

    public function down(): void
    {
        Schema::table('fabric_sample_formulations', function (Blueprint $table) {
            $table->dropUnique('fsf_knit_request_unique');
            $table->dropConstrainedForeignId('knitting_sample_request_id');
            $table->dropColumn('color_index');
        });
    }
};
