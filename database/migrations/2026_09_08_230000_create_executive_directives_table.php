<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Executive directives: action items the Vice President issues to
 * departments/managers with deadlines and tracked completion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executive_directives', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('assigned_to', 64); // module key or department
            $table->date('due_date')->nullable();
            $table->string('priority', 16)->default('normal'); // low|normal|high|urgent
            // open|in_progress|done|overdue
            $table->string('status', 32)->default('open');
            $table->text('completion_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_directives');
    }
};
