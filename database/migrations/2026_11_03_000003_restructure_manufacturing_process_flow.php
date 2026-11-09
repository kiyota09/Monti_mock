<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $legacyRoles = [
        'knitting_yarn', 'knitting_mechanic', 'dyeing_color', 'dyeing_fabric_softener',
        'dyeing_squeezer', 'dyeing_ironing', 'dyeing_lab_chemist', 'dyeing_forming',
        'finishing_packaging', 'maintenance_checker', 'pollution_control_operator',
        'safety_officer', 'boiler_operator', 'checker_quality', 'dyeing_bleaching',
        'finishing_squeezer', 'finishing_ironing',
    ];

    private array $finalRoles = [
        'knitting_yarn', 'knitting_mechanic', 'dyeing_color', 'dyeing_bleaching',
        'dyeing_lab_chemist', 'finishing_squeezer', 'finishing_ironing',
        'finishing_packaging', 'checker_quality', 'maintenance_checker',
        'pollution_control_operator', 'safety_officer', 'boiler_operator', 'dyeing_forming',
    ];

    public function up(): void
    {
        Schema::table('fabrics', function (Blueprint $table) {
            $table->enum('status', ['pending', 'dyeing', 'softener', 'bleaching', 'squeezer', 'iron', 'forming', 'packed', 'rejected'])->default('pending')->change();
        });
        DB::table('fabrics')->where('status', 'softener')->update(['status' => 'bleaching']);
        Schema::table('fabrics', function (Blueprint $table) {
            $table->enum('status', ['pending', 'dyeing', 'bleaching', 'squeezer', 'iron', 'forming', 'packed', 'rejected'])->default('pending')->change();
        });

        Schema::table('squeezer_jobs', function (Blueprint $table) {
            $table->foreignId('fabric_id')->nullable()->after('softener_job_id')->constrained()->cascadeOnDelete();
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('UPDATE squeezer_jobs sj INNER JOIN softener_jobs so ON so.id = sj.softener_job_id SET sj.fabric_id = so.fabric_id');
            DB::statement('ALTER TABLE `squeezer_jobs` MODIFY `softener_job_id` BIGINT UNSIGNED NULL');
        } else {
            $fabricIds = DB::table('squeezer_jobs')
                ->join('softener_jobs', 'softener_jobs.id', '=', 'squeezer_jobs.softener_job_id')
                ->select('squeezer_jobs.id', 'softener_jobs.fabric_id')
                ->get();
            foreach ($fabricIds as $row) {
                DB::table('squeezer_jobs')->where('id', $row->id)->update(['fabric_id' => $row->fabric_id]);
            }
            Schema::table('squeezer_jobs', function (Blueprint $table) {
                $table->foreignId('softener_job_id')->nullable()->change();
            });
        }

        Schema::create('bleaching_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fabric_id')->constrained()->cascadeOnDelete();
            $table->string('bleaching_method', 120);
            $table->text('remarks')->nullable();
            $table->foreignId('operator_id')->constrained('users');
            $table->string('shift');
            $table->string('code')->unique();
            $table->timestamp('processed_at');
            $table->enum('status', ['bleached', 'rebleach'])->default('bleached');
            $table->timestamps();
        });

        $this->migrateRoleEnumsAndAssignments();
        $this->migratePagePermissions();
        $this->migrateTaskAssignments();
    }

    public function down(): void
    {
        // Role and page assignments are retained to avoid losing access edits.
        // Historical production records are intentionally preserved.
    }

    private function migrateRoleEnumsAndAssignments(): void
    {
        $this->setRoleEnum('users', $this->legacyRoles);
        $this->setRoleEnum('manufacturing_supervisor_roles', $this->legacyRoles);

        foreach ([
            'dyeing_fabric_softener' => 'dyeing_bleaching',
            'dyeing_squeezer' => 'finishing_squeezer',
            'dyeing_ironing' => 'finishing_ironing',
        ] as $oldRole => $newRole) {
            DB::table('users')->where('manufacturing_role', $oldRole)->update(['manufacturing_role' => $newRole]);
            $legacyGrants = DB::table('manufacturing_supervisor_roles')->where('manufacturing_role', $oldRole)->get();
            foreach ($legacyGrants as $grant) {
                DB::table('manufacturing_supervisor_roles')->insertOrIgnore([
                    'user_id' => $grant->user_id,
                    'manufacturing_role' => $newRole,
                    'created_at' => $grant->created_at,
                    'updated_at' => now(),
                ]);
            }
            DB::table('manufacturing_supervisor_roles')->where('manufacturing_role', $oldRole)->delete();
        }

        DB::table('manufacturing_supervisor_roles')
            ->whereIn('manufacturing_role', ['finishing_squeezer', 'finishing_ironing', 'finishing_packaging', 'checker_quality'])
            ->whereNotIn('user_id', DB::table('users')->select('id')->where('is_manufacturing_supervisor', true)->where('supervisor_department', 'finishing'))
            ->delete();

        $finishingSupervisors = DB::table('users')->where('is_manufacturing_supervisor', true)->where('supervisor_department', 'finishing')->pluck('id');
        foreach ($finishingSupervisors as $userId) {
            foreach (['finishing_squeezer', 'finishing_ironing'] as $role) {
                DB::table('manufacturing_supervisor_roles')->insertOrIgnore([
                    'user_id' => $userId,
                    'manufacturing_role' => $role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $dyeingSupervisors = DB::table('users')->where('is_manufacturing_supervisor', true)->where('supervisor_department', 'dyeing')->pluck('id');
        foreach ($dyeingSupervisors as $userId) {
            DB::table('manufacturing_supervisor_roles')->insertOrIgnore([
                'user_id' => $userId,
                'manufacturing_role' => 'dyeing_bleaching',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->setRoleEnum('users', $this->finalRoles);
        $this->setRoleEnum('manufacturing_supervisor_roles', $this->finalRoles);
    }

    private function migratePagePermissions(): void
    {
        foreach ([
            'dyeing_fabric_softener' => 'dyeing_bleaching',
            'dyeing_squeezer' => 'finishing_squeezer',
            'dyeing_ironing' => 'finishing_ironing',
        ] as $oldPage => $newPage) {
            $rows = DB::table('page_permissions')->where('module', 'MAN')->where('page', $oldPage)->get();
            foreach ($rows as $row) {
                DB::table('page_permissions')->insertOrIgnore([
                    'user_id' => $row->user_id,
                    'module' => $row->module,
                    'page' => $newPage,
                    'permission_level' => $row->permission_level,
                    'created_at' => $row->created_at,
                    'updated_at' => now(),
                ]);
            }
            DB::table('page_permissions')->where('module', 'MAN')->where('page', $oldPage)->delete();
        }
    }

    private function migrateTaskAssignments(): void
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

    private function setRoleEnum(string $table, array $values): void
    {
        $list = implode(',', array_map(fn ($value) => "'{$value}'", $values));
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `{$table}` MODIFY `manufacturing_role` ENUM({$list}) NULL");

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($values) {
            $blueprint->enum('manufacturing_role', $values)->nullable()->change();
        });
    }
};
