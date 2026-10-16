<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secretary workspace (executive-office standards):
 * correspondence/document control registry, meeting & appointment
 * scheduler, and office memos/announcements. Position-gated
 * (secretary / general_manager, CEO bypass) — not module-gated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretary_documents', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no')->unique();
            $table->string('direction', 16)->default('incoming'); // incoming|outgoing
            $table->string('sender')->nullable();
            $table->string('recipient')->nullable();
            $table->string('subject');
            $table->string('concerned_module', 16)->nullable(); // HRM, MAN, ...
            $table->date('received_date');
            $table->date('deadline')->nullable();
            $table->string('status', 32)->default('logged'); // logged|forwarded|in_progress|filed|closed
            $table->string('file_path')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('secretary_meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('meeting_date');
            $table->string('start_time', 5)->nullable(); // H:i
            $table->string('end_time', 5)->nullable();
            $table->string('venue')->nullable();
            $table->string('organizer')->nullable();
            $table->text('attendees')->nullable();
            $table->text('agenda')->nullable();
            $table->string('status', 32)->default('scheduled'); // scheduled|ongoing|done|cancelled
            $table->text('minutes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('secretary_memos', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no')->unique();
            $table->string('title');
            $table->text('body');
            $table->string('audience', 64)->default('all'); // all|module key|department
            $table->string('priority', 16)->default('normal'); // normal|urgent
            $table->string('status', 32)->default('draft'); // draft|published|archived
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretary_memos');
        Schema::dropIfExists('secretary_meetings');
        Schema::dropIfExists('secretary_documents');
    }
};
