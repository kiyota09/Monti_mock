<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documents are now also uploaded directly on a pending client from the
     * CRM Approval board (no deal yet), so the deal reference must be optional.
     */
    public function up(): void
    {
        Schema::table('crm_opportunity_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('opportunity_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('crm_opportunity_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('opportunity_id')->nullable(false)->change();
        });
    }
};
