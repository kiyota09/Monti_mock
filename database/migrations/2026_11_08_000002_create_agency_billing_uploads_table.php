<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_billing_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamps();
            $table->index(['agency_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_billing_uploads');
    }
};
