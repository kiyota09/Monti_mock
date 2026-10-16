<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERP-grade reporting line: users.manager_id self-references the active
     * user occupying the parent position (hrm_positions.reports_to_id).
     * Nullable for BC; legacy immediate_supervisor / department_head strings
     * stay as display fallbacks. Reassignments cascade by updating this one
     * FK instead of stale name strings.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'manager_id')) {
                $table->foreignId('manager_id')->nullable()->after('hrm_employment_type_id')->constrained('users')->nullOnDelete();
            }
        });

        // Backfill from the legacy name strings where they match an active user.
        foreach (\DB::table('users')->select('id', 'immediate_supervisor', 'department_head', 'manager_id')->get() as $u) {
            if ($u->manager_id) {
                continue;
            }
            $name = $u->immediate_supervisor ?: $u->department_head;
            if (! $name) {
                continue;
            }
            $managerId = \DB::table('users')->where('name', $name)->where('is_active', true)->value('id');
            if ($managerId && (int) $managerId !== (int) $u->id) {
                \DB::table('users')->where('id', $u->id)->update(['manager_id' => $managerId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
        });
    }
};
