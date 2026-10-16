<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carry the target manufacturing department on executive position
     * commands so supervisor assignment/removal flows through the
     * CEO → IT chain (replaces the removed MAN access page).
     *
     * Convention: requested_position = 'supervisor' + supervisor_department
     * set  →  assign/promote to that department seat.
     * requested_position = 'staff' with action = 'remove_supervisor'
     *  →  strip the supervisor flag (target stays MAN staff).
     */
    public function up(): void
    {
        Schema::table('position_change_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('position_change_requests', 'supervisor_department')) {
                $table->string('supervisor_department', 32)->nullable()->after('requested_role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('position_change_requests', function (Blueprint $table) {
            $table->dropColumn('supervisor_department');
        });
    }
};
