<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_email_messages')) {
            return;
        }

        Schema::table('crm_email_messages', function (Blueprint $table) {
            $table->index('message_id', 'crm_email_messages_message_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('crm_email_messages', function (Blueprint $table) {
            $table->dropIndex('crm_email_messages_message_id_index');
        });
    }
};
