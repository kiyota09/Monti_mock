<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Files/images sent with client feedback & complaints.
     * The client Support form already posts a file input, but nothing ever
     * stored it; CRM staff can also attach visit photos from the
     * Investigation page. Both now land here and render in the detail modal.
     */
    public function up(): void
    {
        Schema::create('crm_feedback_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->constrained('crm_feedback')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime', 64)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('feedback_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_feedback_attachments');
    }
};
