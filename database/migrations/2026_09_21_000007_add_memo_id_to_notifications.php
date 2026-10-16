<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link memo notifications straight to their memo so tapping the
     * bell opens the memo itself (not a dead-end notification).
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'memo_id')) {
                $table->unsignedBigInteger('memo_id')->nullable()->after('link_route');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('memo_id');
        });
    }
};
