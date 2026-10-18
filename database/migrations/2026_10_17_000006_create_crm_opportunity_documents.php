<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Files/images uploaded from a pipeline card (only offered on stages that
     * have `allows_uploads` enabled). Each row is tied to the deal it was
     * uploaded from AND to the client on that deal, so the same documents can
     * be surfaced on the client's record too.
     */
    public function up(): void
    {
        Schema::create('crm_opportunity_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('crm_opportunities')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('crm_stages')->nullOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime', 64)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('opportunity_id');
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunity_documents');
    }
};
