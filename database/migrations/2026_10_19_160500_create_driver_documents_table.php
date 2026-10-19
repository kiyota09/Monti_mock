<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->enum('document_type', [
                'license', 'medical', 'hazmat',
                'vehicle_registration', 'vehicle_insurance', 'vehicle_permit', 'other',
            ])->default('other');
            $table->string('title');
            $table->string('file_path')->nullable();
            $table->string('number')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_documents');
    }
};
