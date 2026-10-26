<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Problem reports filed from the TopBar Settings → Help Center page.
     * Visible to the reporter (Help Center "My reports") and to IT/HR
     * admins directly in the database (an IT triage queue can follow).
     */
    public function up(): void
    {
        Schema::create('problem_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject', 255);
            $table->string('category', 50)->default('bug');
            $table->text('description');
            $table->string('page_url', 500)->nullable();
            $table->string('status', 30)->default('open');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_reports');
    }
};
