<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users'); // Who did it
            $table->foreignId('target_id')->constrained('users'); // Who was affected
            $table->string('action'); // deactivate, reactivate, update
            $table->text('reason')->nullable(); // The "Why" from your modal
            $table->string('target_name'); // For quick display
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
