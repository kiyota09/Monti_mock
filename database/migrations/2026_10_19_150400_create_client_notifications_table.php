<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('type')->default('logistics');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link_route')->nullable();
            $table->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['client_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_notifications');
    }
};
