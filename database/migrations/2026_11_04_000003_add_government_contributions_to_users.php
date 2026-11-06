<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'sss_contribution')) {
                $table->decimal('sss_contribution', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('users', 'pagibig_contribution')) {
                $table->decimal('pagibig_contribution', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('users', 'philhealth_contribution')) {
                $table->decimal('philhealth_contribution', 12, 2)->nullable();
            }
        });

        DB::table('users')->whereNotNull('sss_id')->update([
            'sss_contribution' => DB::raw('CAST(sss_id AS DECIMAL(12,2))'),
        ]);
        DB::table('users')->whereNotNull('pagibig_id')->update([
            'pagibig_contribution' => DB::raw('CAST(pagibig_id AS DECIMAL(12,2))'),
        ]);
        DB::table('users')->whereNotNull('philhealth_id')->update([
            'philhealth_contribution' => DB::raw('CAST(philhealth_id AS DECIMAL(12,2))'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = ['sss_contribution', 'pagibig_contribution', 'philhealth_contribution'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
