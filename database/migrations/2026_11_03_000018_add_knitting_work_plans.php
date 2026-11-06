<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('man_task_assignments', function (Blueprint $table) {
            $table->decimal('assigned_weight', 10, 2)->nullable()->after('notes');
            $table->json('machine_ids')->nullable()->after('assigned_weight');
        });

        Schema::table('knitting_machine_setups', function (Blueprint $table) {
            $table->foreignId('requested_by')->nullable()->after('operator_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('knitting_machine_setups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by');
        });

        Schema::table('man_task_assignments', function (Blueprint $table) {
            $table->dropColumn(['assigned_weight', 'machine_ids']);
        });
    }
};
