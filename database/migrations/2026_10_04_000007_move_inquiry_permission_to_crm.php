<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Client inquiries moved from ECO to CRM (highly customized orders
     * belong with the relationship workflow). Existing per-page grants
     * follow the page: ECO/inquiry rows become CRM/inquiry rows so nobody
     * silently loses (or keeps the wrong) access.
     */
    public function up(): void
    {
        DB::table('page_permissions')
            ->where('page', 'inquiry')
            ->whereIn('module', ['ECO', 'eco', 'Eco'])
            ->update(['module' => 'CRM']);
    }

    public function down(): void
    {
        // Intentional no-op: moved rows are indistinguishable from native
        // CRM grants, so rolling back would guess. Re-running up() stays
        // idempotent instead.
    }
};
