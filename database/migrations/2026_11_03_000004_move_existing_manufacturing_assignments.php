<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('man_task_assignments')) {
            return;
        }

        foreach ([
            ['old' => ['dyeing_fabric_softener', 'dyeing_softener', 'softener'], 'stage' => 'dyeing_bleaching', 'role' => 'dyeing_bleaching', 'department' => 'dyeing'],
            ['old' => ['dyeing_squeezer', 'squeezer'], 'stage' => 'finishing_squeezer', 'role' => 'finishing_squeezer', 'department' => 'finishing'],
            ['old' => ['dyeing_ironing', 'ironing', 'iron'], 'stage' => 'finishing_ironing', 'role' => 'finishing_ironing', 'department' => 'finishing'],
        ] as $mapping) {
            DB::table('man_task_assignments')->whereIn('stage', $mapping['old'])->update([
                'department' => $mapping['department'],
                'stage' => $mapping['stage'],
                'assigned_role' => $mapping['role'],
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Existing assignments remain with the new department owners.
    }
};
