<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eco_conversation_attachments', function (Blueprint $table) {
            $table->id();
            // Links to your existing conversation_messages table
            $table->foreignId('conversation_message_id')
                ->constrained('conversation_messages')
                ->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type'); // image/png, application/pdf, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eco_conversation_attachments');
    }
};