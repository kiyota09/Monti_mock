<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The packaging role moves out of the Dyeing department into Finishing, so the
 * slug `dyeing_packaging` is renamed to `finishing_packaging` on every table
 * that stores a manufacturing role.
 *
 * MySQL cannot ALTER an enum while rows still hold to-be-removed values, so the
 * columns are first widened to a superset (old + new), the data is migrated,
 * then the columns are narrowed to the final value set.
 */
return new class extends Migration
{
    private array $superset = [
        'knitting_yarn',
        'knitting_mechanic',
        'dyeing_color',
        'dyeing_fabric_softener',
        'dyeing_squeezer',
        'dyeing_ironing',
        'dyeing_lab_chemist',
        'dyeing_forming',
        'dyeing_packaging',
        'finishing_packaging',
        'maintenance_checker',
        'pollution_control_operator',
        'safety_officer',
        'boiler_operator',
        'checker_quality',
    ];

    private array $final = [
        'knitting_yarn',
        'knitting_mechanic',
        'dyeing_color',
        'dyeing_fabric_softener',
        'dyeing_squeezer',
        'dyeing_ironing',
        'dyeing_lab_chemist',
        'dyeing_forming',
        'finishing_packaging',
        'maintenance_checker',
        'pollution_control_operator',
        'safety_officer',
        'boiler_operator',
        'checker_quality',
    ];

    private array $original = [
        'knitting_yarn',
        'knitting_mechanic',
        'dyeing_color',
        'dyeing_fabric_softener',
        'dyeing_squeezer',
        'dyeing_ironing',
        'dyeing_lab_chemist',
        'dyeing_forming',
        'dyeing_packaging',
        'maintenance_checker',
        'pollution_control_operator',
        'safety_officer',
        'boiler_operator',
        'checker_quality',
    ];

    public function up(): void
    {
        $this->setEnum('users', $this->superset);
        $this->setEnum('manufacturing_supervisor_roles', $this->superset);

        DB::table('users')
            ->where('manufacturing_role', 'dyeing_packaging')
            ->update(['manufacturing_role' => 'finishing_packaging']);

        DB::table('manufacturing_supervisor_roles')
            ->where('manufacturing_role', 'dyeing_packaging')
            ->update(['manufacturing_role' => 'finishing_packaging']);

        // A dyeing supervisor used to own the packaging role; the finishing
        // supervisor now must. Drop the moved grant from anyone who is not a
        // finishing supervisor, then grant it to the finishing supervisor(s).
        DB::table('manufacturing_supervisor_roles')
            ->where('manufacturing_role', 'finishing_packaging')
            ->whereNotIn('user_id', function ($query) {
                $query->select('id')->from('users')
                    ->where('is_manufacturing_supervisor', true)
                    ->where('supervisor_department', 'finishing');
            })
            ->delete();

        $finishingSupervisors = DB::table('users')
            ->where('is_manufacturing_supervisor', true)
            ->where('supervisor_department', 'finishing')
            ->pluck('id');

        foreach ($finishingSupervisors as $supervisorId) {
            $exists = DB::table('manufacturing_supervisor_roles')
                ->where('user_id', $supervisorId)
                ->where('manufacturing_role', 'finishing_packaging')
                ->exists();

            if (! $exists) {
                DB::table('manufacturing_supervisor_roles')->insert([
                    'user_id' => $supervisorId,
                    'manufacturing_role' => 'finishing_packaging',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // `checker_quality` is a Finishing role: strip any stale grant held by
        // another department's supervisor (e.g. the dyeing supervisor).
        DB::table('manufacturing_supervisor_roles')
            ->where('manufacturing_role', 'checker_quality')
            ->whereNotIn('user_id', function ($query) {
                $query->select('id')->from('users')
                    ->where('is_manufacturing_supervisor', true)
                    ->where('supervisor_department', 'finishing');
            })
            ->delete();

        $this->setEnum('users', $this->final);
        $this->setEnum('manufacturing_supervisor_roles', $this->final);
    }

    public function down(): void
    {
        $this->setEnum('users', $this->superset);
        $this->setEnum('manufacturing_supervisor_roles', $this->superset);

        DB::table('users')
            ->where('manufacturing_role', 'finishing_packaging')
            ->update(['manufacturing_role' => 'dyeing_packaging']);

        DB::table('manufacturing_supervisor_roles')
            ->where('manufacturing_role', 'finishing_packaging')
            ->update(['manufacturing_role' => 'dyeing_packaging']);

        $this->setEnum('users', $this->original);
        $this->setEnum('manufacturing_supervisor_roles', $this->original);
    }

    private function setEnum(string $table, array $values): void
    {
        $list = implode(",", array_map(fn ($v) => "'{$v}'", $values));

        DB::statement("ALTER TABLE `{$table}` MODIFY `manufacturing_role` ENUM({$list}) NULL");
    }
};
