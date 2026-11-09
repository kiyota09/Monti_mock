<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $roles = [
        'knitting_yarn', 'knitting_mechanic', 'knitting_inspector', 'knitting_sample',
        'dyeing_color', 'dyeing_bleaching', 'dyeing_pre_treatment', 'dyeing_treatment', 'dyeing_lab_chemist',
        'finishing_squeezer', 'finishing_ironing', 'finishing_hydro', 'finishing_tubetex',
        'finishing_packaging', 'finishing_inspector', 'checker_quality', 'maintenance_checker',
        'pollution_control_operator', 'safety_officer', 'boiler_operator', 'dyeing_forming',
    ];

    public function up(): void
    {
        $this->setRoleEnum('users');
        $this->setRoleEnum('manufacturing_supervisor_roles');

        $supervisors = DB::table('users')->where('is_manufacturing_supervisor', true)
            ->where('supervisor_department', 'finishing')->pluck('id');
        foreach ($supervisors as $userId) {
            foreach (['finishing_hydro', 'finishing_tubetex'] as $role) {
                DB::table('manufacturing_supervisor_roles')->insertOrIgnore([
                    'user_id' => $userId,
                    'manufacturing_role' => $role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep role assignments intact; removing either role could strand accounts.
    }

    private function setRoleEnum(string $table): void
    {
        if (DB::getDriverName() === 'mysql') {
            $list = implode(',', array_map(fn ($role) => "'{$role}'", $this->roles));
            DB::statement("ALTER TABLE `{$table}` MODIFY `manufacturing_role` ENUM({$list}) NULL");

            return;
        }

        $roles = $this->roles;
        Schema::table($table, function (Blueprint $blueprint) use ($roles) {
            $blueprint->enum('manufacturing_role', $roles)->nullable()->change();
        });
    }

};
