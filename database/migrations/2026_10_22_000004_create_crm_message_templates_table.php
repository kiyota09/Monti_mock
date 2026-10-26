<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_message_templates')) {
            return;
        }

        Schema::create('crm_message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Every page owns a separate template store
            // (emails, leads, opportunities, inquiries, approvals).
            $table->string('page_key', 40);
            $table->string('title');
            $table->text('body');
            $table->timestamps();

            $table->index(['user_id', 'page_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_message_templates');
    }
};
