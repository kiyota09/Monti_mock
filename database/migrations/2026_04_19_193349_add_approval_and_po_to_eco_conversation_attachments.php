<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eco_conversation_attachments', function (Blueprint $table) {
            $table->boolean('approved_by_client')->default(false)->after('file_type');
            $table->boolean('is_po')->default(false)->after('approved_by_client');
        });
    }

    public function down(): void
    {
        Schema::table('eco_conversation_attachments', function (Blueprint $table) {
            $table->dropColumn(['approved_by_client', 'is_po']);
        });
    }
};