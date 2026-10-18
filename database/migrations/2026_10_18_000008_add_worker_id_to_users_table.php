<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agency workers get a login account on deployment (users row). Mark it
     * with worker_id so worker accounts are never mixed into the Monti
     * Employee roster, and align their identifier with the WRK-xxx scheme
     * (Monti employees stay on EMP-xxx).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('worker_id')->nullable()->after('employee_id')
                ->constrained('workers')->nullOnDelete();
        });

        // Backfill existing worker accounts: they carry the worker's exact
        // name (the login is created straight from the worker record). Only
        // worker_id is set — employee_id stays untouched because it is
        // unique and two logins can share a worker's name.
        DB::table('users as u')
            ->join('workers as w', DB::raw('LOWER(TRIM(u.name))'), '=', DB::raw('LOWER(TRIM(w.name))'))
            ->whereNull('u.worker_id')
            ->update([
                'u.worker_id' => DB::raw('w.id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('worker_id');
        });
    }
};
