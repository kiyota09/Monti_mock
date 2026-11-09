<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $roles = [
        'knitting_yarn', 'knitting_mechanic', 'knitting_inspector',
        'dyeing_color', 'dyeing_bleaching', 'dyeing_lab_chemist',
        'finishing_squeezer', 'finishing_ironing', 'finishing_packaging', 'finishing_inspector',
        'checker_quality', 'maintenance_checker', 'pollution_control_operator',
        'safety_officer', 'boiler_operator', 'dyeing_forming',
    ];

    public function up(): void
    {
        $this->setRoleEnum('users');
        $this->setRoleEnum('manufacturing_supervisor_roles');

        foreach ([
            ['department' => 'knitting', 'role' => 'knitting_inspector'],
            ['department' => 'finishing', 'role' => 'finishing_inspector'],
        ] as $grant) {
            $supervisors = DB::table('users')
                ->where('is_manufacturing_supervisor', true)
                ->where('supervisor_department', $grant['department'])
                ->pluck('id');

            foreach ($supervisors as $userId) {
                DB::table('manufacturing_supervisor_roles')->insertOrIgnore([
                    'user_id' => $userId,
                    'manufacturing_role' => $grant['role'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Inspector accounts and grants are retained to avoid breaking access records.
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
