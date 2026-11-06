<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (! Schema::hasColumn('payrolls', 'cutoff_start')) {
                $table->date('cutoff_start')->nullable();
            }
            if (! Schema::hasColumn('payrolls', 'cutoff_end')) {
                $table->date('cutoff_end')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('payrolls', 'cutoff_start')) {
                $table->dropColumn('cutoff_start');
            }
            if (Schema::hasColumn('payrolls', 'cutoff_end')) {
                $table->dropColumn('cutoff_end');
            }
        });
    }
};
