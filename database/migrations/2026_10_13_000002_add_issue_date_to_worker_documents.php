<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track when a supporting certificate was obtained (start/issue date)
     * in addition to its expiry date.
     */
    public function up(): void
    {
        Schema::table('worker_documents', function (Blueprint $table) {
            $table->date('issue_date')->nullable()->after('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('worker_documents', function (Blueprint $table) {
            $table->dropColumn('issue_date');
        });
    }
};
