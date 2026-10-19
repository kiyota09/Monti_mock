<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_messages')) {
            return;
        }

        Schema::table('client_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('client_messages', 'type')) {
                $table->string('type', 20)->default('text')->after('sender_contact_id');
            }
            if (! Schema::hasColumn('client_messages', 'call_type')) {
                $table->string('call_type', 10)->nullable()->after('type');
                $table->string('call_status', 20)->nullable()->after('call_type');
                $table->unsignedInteger('call_duration')->nullable()->after('call_status');
                $table->timestamp('call_ended_at')->nullable()->after('call_duration');
            }
            if (! Schema::hasColumn('client_messages', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('body');
                $table->string('attachment_name')->nullable()->after('attachment_path');
                $table->string('attachment_mime', 150)->nullable()->after('attachment_name');
                $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
            }
            if (! Schema::hasColumn('client_messages', 'reply_to_id')) {
                $table->foreignId('reply_to_id')->nullable()->after('attachment_size')
                    ->constrained('client_messages')->nullOnDelete();
            }
            if (! Schema::hasColumn('client_messages', 'deleted_for_everyone_at')) {
                $table->timestamp('deleted_for_everyone_at')->nullable()->after('is_read');
                $table->boolean('deleted_for_crm')->default(false)->after('deleted_for_everyone_at');
                $table->boolean('deleted_for_client')->default(false)->after('deleted_for_crm');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_messages')) {
            return;
        }

        Schema::table('client_messages', function (Blueprint $table) {
            if (Schema::hasColumn('client_messages', 'reply_to_id')) {
                $table->dropConstrainedForeignId('reply_to_id');
            }
        });

        Schema::table('client_messages', function (Blueprint $table) {
            $columns = ['deleted_for_client', 'deleted_for_crm', 'deleted_for_everyone_at', 'attachment_size',
                'attachment_mime', 'attachment_name', 'attachment_path', 'call_ended_at', 'call_duration',
                'call_status', 'call_type', 'type'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('client_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
