<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soft-delete worker supporting files: agency "X" only sets
     * deleted_at — rows stay in the database (recoverable).
     */
    public function up(): void
    {
        Schema::table('worker_documents', function (Blueprint $table) {
            $table->softDeletes()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('worker_documents', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
