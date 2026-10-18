<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM pipeline: Lost-stage flag + deal archiving.
 *
 * - crm_stages.is_lost: mirrors is_won, marks a terminal "Lost" column.
 *   Deals in a lost stage can no longer be scheduled; only archived.
 * - crm_opportunities.archived_at: deal flagged as totally failed and
 *   hidden from the board, surfaced in the Archives tab instead.
 *
 * Additive / BC-safe: existing stages/opportunities are untouched except
 * a best-effort backfill of the new Lost flag from the column name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_stages', function (Blueprint $table) {
            $table->boolean('is_lost')->default(false)->after('is_won');
        });

        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('lost_reason');
            $table->index('archived_at');
        });

        // Backfill: columns that clearly read as lost/cancelled become Lost stages.
        try {
            DB::table('crm_stages')
                ->where('is_lost', false)
                ->where(function ($q) {
                    $q->where('name', 'like', '%lost%')->orWhere('name', 'like', '%cancel%');
                })
                ->update(['is_lost' => true]);
        } catch (\Throwable $e) {
            // Fresh installs / drivers without LIKE support: safe to ignore.
        }
    }

    public function down(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });

        Schema::table('crm_stages', function (Blueprint $table) {
            $table->dropColumn('is_lost');
        });
    }
};
