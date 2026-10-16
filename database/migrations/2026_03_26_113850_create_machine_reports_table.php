<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->onDelete('cascade');
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            $table->text('issue');
            $table->enum('status', ['pending', 'resolved'])->default('pending');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_reports');
    }
};
