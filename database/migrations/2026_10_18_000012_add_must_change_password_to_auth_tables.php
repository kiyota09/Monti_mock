<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a `must_change_password` flag to every auth model that can receive a
 * system-issued password (temporary / initial / recovered). When true, the
 * portal forces the user to set a new password before using the app.
 *
 * agency_users already has this column (see
 * 2026_10_09_000006_create_attendance_billing_tables.php).
 */
return new class extends Migration
{
    /**
     * Tables that should carry the flag.
     */
    private array $tables = ['users', 'clients', 'applicants', 'suppliers'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'must_change_password')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->boolean('must_change_password')->default(false)->after('password');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'must_change_password')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('must_change_password');
                });
            }
        }
    }
};
