<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->onDelete('cascade');
            $table->enum('sender_type', ['client', 'eco']);   // 'eco' = CEO/Secretary/GM
            $table->text('message');
            $table->string('attachment')->nullable();        // path to file
            $table->json('meeting_data')->nullable();        // { scheduled_at, location, type }
            $table->boolean('is_system_event')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('conversation_messages');
    }
};