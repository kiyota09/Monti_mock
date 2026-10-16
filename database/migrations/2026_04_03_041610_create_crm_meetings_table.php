<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('crm_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->dateTime('scheduled_at');
            $table->string('meeting_type'); // e.g., phone, video, onsite
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['scheduled', 'done', 'cancelled', 'rescheduled'])->default('scheduled');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('crm_meetings');
    }
};