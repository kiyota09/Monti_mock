<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'pagibig_id')) {
                $table->string('pagibig_id', 60)->nullable()->after('sss_id');
            }
            if (! Schema::hasColumn('users', 'philhealth_id')) {
                $table->string('philhealth_id', 60)->nullable()->after('pagibig_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'philhealth_id')) {
                $table->dropColumn('philhealth_id');
            }
            if (Schema::hasColumn('users', 'pagibig_id')) {
                $table->dropColumn('pagibig_id');
            }
        });
    }
};
