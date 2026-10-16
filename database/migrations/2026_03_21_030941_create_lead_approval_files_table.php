<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lead_approval_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->onDelete('cascade');
            $table->string('file_path');
            $table->string('original_name');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_approval_files');
    }
};
