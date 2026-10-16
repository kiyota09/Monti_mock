<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Executive inbox: push-style notifications for the President/VP
 * (pending approvals, critical incidents, compliance flags).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executive_notifications', function (Blueprint $table) {
            $table->id();
            // payroll|vendor|credit|incident|compliance|info
            $table->string('type', 32)->default('info');
            $table->string('title');
            $table->text('body')->nullable();
            // Named route the inbox item deep-links to (CEO module only).
            $table->string('link_route')->nullable();
            $table->boolean('is_read')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_notifications');
    }
};
