<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fabric_sample_requests', 'request_method')) {
            Schema::table('fabric_sample_requests', function (Blueprint $table) {
                $table->string('request_method', 24)->nullable()->after('fabric_name');
                $table->string('carrier_name')->nullable()->after('request_method');
                $table->string('client_reference_path')->nullable()->after('sample_image_path');
                $table->foreignId('pantone_reference_id')->nullable()->after('recipe_id')
                    ->constrained('pantone_references')->nullOnDelete();
                $table->json('pantone_formula')->nullable()->after('formula');
            });
        }

        Schema::table('bom_records', function (Blueprint $table) {
            $table->dropForeign('bom_records_client_id_foreign');
            $table->dropForeign('bom_records_product_id_foreign');
            $table->dropUnique('bom_records_client_id_product_id_unique');
            $table->foreignId('fabric_sample_request_id')->nullable()->unique()
                ->after('product_id')->constrained('fabric_sample_requests')->nullOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fabric_sample_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pantone_reference_id');
            $table->dropColumn(['request_method', 'carrier_name', 'client_reference_path', 'pantone_formula']);
        });
        Schema::table('bom_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fabric_sample_request_id');
            $table->dropForeign('bom_records_client_id_foreign');
            $table->dropForeign('bom_records_product_id_foreign');
            $table->unique(['client_id', 'product_id']);
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};
