<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('man_task_assignments', function (Blueprint $table) {
            $table->date('deadline')->nullable()->after('notes')->index();
        });
    }

    public function down(): void
    {
        Schema::table('man_task_assignments', function (Blueprint $table) {
            $table->dropIndex(['deadline']);
            $table->dropColumn('deadline');
        });
    }
};
