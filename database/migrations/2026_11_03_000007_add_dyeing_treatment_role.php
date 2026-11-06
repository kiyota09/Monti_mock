<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $roles = [
        'knitting_yarn', 'knitting_mechanic', 'knitting_inspector', 'knitting_sample',
        'dyeing_color', 'dyeing_bleaching', 'dyeing_treatment', 'dyeing_lab_chemist',
        'finishing_squeezer', 'finishing_ironing', 'finishing_packaging', 'finishing_inspector',
        'checker_quality', 'maintenance_checker', 'pollution_control_operator',
        'safety_officer', 'boiler_operator', 'dyeing_forming',
    ];

    public function up(): void
    {
        $list = implode(',', array_map(fn ($role) => "'{$role}'", $this->roles));
        DB::statement("ALTER TABLE `users` MODIFY `manufacturing_role` ENUM({$list}) NULL");
        DB::statement("ALTER TABLE `manufacturing_supervisor_roles` MODIFY `manufacturing_role` ENUM({$list}) NULL");

        $supervisors = DB::table('users')->where('is_manufacturing_supervisor', true)
            ->where('supervisor_department', 'dyeing')->pluck('id');
        foreach ($supervisors as $userId) {
            DB::table('manufacturing_supervisor_roles')->insertOrIgnore([
                'user_id' => $userId,
                'manufacturing_role' => 'dyeing_treatment',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep role assignments intact; removing this role could strand accounts.
    }
};
