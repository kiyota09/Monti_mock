<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Group-chat management + file sharing for the top-bar messenger:
     * - message_threads.photo_path (group photo, groups only)
     * - chat_messages file attachment columns (images + documents)
     */
    public function up(): void
    {
        Schema::table('message_threads', function (Blueprint $table) {
            if (! Schema::hasColumn('message_threads', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('title');
            }
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_messages', 'file_path')) {
                $table->string('file_path')->nullable()->after('body');
                $table->string('file_name')->nullable()->after('file_path');
                $table->string('file_type')->nullable()->after('file_name');
            }
        });

        // Body becomes optional (file-only messages allowed).
        DB::statement('ALTER TABLE `chat_messages` MODIFY COLUMN `body` TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `chat_messages` MODIFY COLUMN `body` TEXT NOT NULL');

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'file_name', 'file_type']);
        });
        Schema::table('message_threads', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
