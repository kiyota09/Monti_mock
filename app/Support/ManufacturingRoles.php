<?php

namespace App\Support;

/**
 * Single source of truth for the Manufacturing (MAN) department / role model.
 *
 * Kept in sync with:
 *  - User::getSupervisedRolesAttribute()
 *  - App\Http\Middleware\CheckManufacturingRoleAccess
 *  - App\Http\Controllers\It\ItAccessControlController
 *  - App\Http\Controllers\Ceo\CeoAccessController
 *  - resources/js/Layouts/modules/SidebarMan.vue
 *  - resources/js/Pages/Dashboard/IT/Manager/ItAccessControl.vue
 */
class ManufacturingRoles
{
    /**
     * Production departments that a Job Order flows through, in order.
     */
    public const PRODUCTION_DEPARTMENTS = ['knitting', 'dyeing', 'finishing'];

    /**
     * Department slug => ordered manufacturing_role slugs it oversees.
     */
    public const DEPARTMENTS = [
        'knitting' => [
            'knitting_yarn',
            'knitting_mechanic',
            'knitting_inspector',
            'knitting_sample',
        ],
        'dyeing' => [
            'dyeing_color',
            'dyeing_bleaching',
            'dyeing_pre_treatment',
            'dyeing_treatment',
            'dyeing_lab_chemist',
        ],
        'finishing' => [
            'finishing_squeezer',
            'finishing_ironing',
            'finishing_hydro',
            'finishing_tubetex',
            'finishing_packaging',
            'finishing_inspector',
            'checker_quality',
        ],
        'maintenance' => [
            'maintenance_checker',
            'pollution_control_operator',
            'safety_officer',
        ],
        'boiler' => [
            'boiler_operator',
        ],
    ];

    /**
     * Ordered process stages a Job Order passes through per department.
     * The value is the role slug that performs the stage.
     */
    public const STAGES = [
        'knitting' => [
            'knitting' => 'knitting_yarn',
        ],
        'dyeing' => [
            'dyeing_bleaching' => 'dyeing_bleaching',
            'dyeing_pre_treatment' => 'dyeing_pre_treatment',
            'dyeing_color' => 'dyeing_color',
            'dyeing_treatment' => 'dyeing_treatment',
        ],
        'finishing' => [
            'water_extraction_squeezer' => 'finishing_squeezer',
            'water_extraction_hydro' => 'finishing_hydro',
            'drying_heatsetting' => 'finishing_ironing',
            'drying_tubetex' => 'finishing_tubetex',
            'shrinkage_inspection' => 'finishing_inspector',
            'quality_check' => 'checker_quality',
            'finishing_packaging' => 'finishing_packaging',
        ],
    ];

    /**
     * Fabric status a Job Order's fabric must have before a stage becomes
     * assignable. The dyeing department follows a strict
     * colour → bleaching → finishing squeezer → finishing ironing sequence, so each stage only
     * opens once the fabric reaches the matching status.
     */
    public const STAGE_REQUIRES_FABRIC_STATUS = [
        'dyeing_color' => 'dyeing',
        'dyeing_bleaching' => 'bleaching',
        'dyeing_pre_treatment' => 'pre_treatment',
        'dyeing_treatment' => 'post_treatment',
        'finishing_squeezer' => 'squeezer',
        'finishing_ironing' => 'iron',
    ];

    /**
     * Human-readable labels for every manufacturing role slug.
     */
    public const LABELS = [
        'knitting_yarn' => 'Knitting Yarn Staff',
        'knitting_mechanic' => 'Knitting Mechanic Staff',
        'knitting_inspector' => 'Knitting Inspector Staff',
        'knitting_sample' => 'Knitting Sample Staff',
        'dyeing_color' => 'Dyeing Color Staff',
        'dyeing_bleaching' => 'Dyeing Bleaching Staff',
        'dyeing_pre_treatment' => 'Dyeing Pre Treatment Staff',
        'dyeing_treatment' => 'Dyeing Post Treatment Staff',
        'finishing_squeezer' => 'Finishing Squeezer Staff',
        'finishing_ironing' => 'Finishing Heatsetting Staff',
        'finishing_hydro' => 'Finishing Hydro Staff',
        'finishing_tubetex' => 'Finishing Tubetex Staff',
        'dyeing_lab_chemist' => 'Dyeing Lab Chemist',
        'finishing_packaging' => 'Finishing Packaging Staff',
        'finishing_inspector' => 'Finishing Shrinkage Inspector',
        'checker_quality' => 'Finishing Quality Checker',
        'maintenance_checker' => 'Maintenance Checker Staff',
        'pollution_control_operator' => 'Pollution Control Operator',
        'safety_officer' => 'Safety Officer Staff',
        'boiler_operator' => 'Boiler Operator Staff',
    ];

    public const DEPARTMENT_LABELS = [
        'knitting' => 'Knitting Department',
        'dyeing' => 'Dyeing Department',
        'finishing' => 'Finishing Department',
        'maintenance' => 'Maintenance Department',
        'boiler' => 'Boiler Department',
    ];

    /**
     * All manufacturing_role slugs, flattened from DEPARTMENTS.
     *
     * @return array<int, string>
     */
    public static function allRoles(): array
    {
        return array_merge(...array_values(self::DEPARTMENTS));
    }

    /**
     * Roles overseen by a department.
     *
     * @return array<int, string>
     */
    public static function rolesForDepartment(?string $department): array
    {
        if (! $department) {
            return [];
        }

        return self::DEPARTMENTS[$department] ?? [];
    }

    /**
     * Department that owns a given manufacturing_role slug, or null.
     */
    public static function departmentForRole(?string $role): ?string
    {
        if (! $role) {
            return null;
        }

        foreach (self::DEPARTMENTS as $department => $roles) {
            if (in_array($role, $roles, true)) {
                return $department;
            }
        }

        return null;
    }

    /**
     * Stages (stage => role) for a department.
     *
     * @return array<string, string>
     */
    public static function stagesForDepartment(?string $department): array
    {
        if (! $department) {
            return [];
        }

        return self::STAGES[$department] ?? [];
    }

    /**
     * Every stage slug across all production departments, in flow order.
     *
     * @return array<int, string>
     */
    public static function allStages(): array
    {
        $stages = [];
        foreach (self::PRODUCTION_DEPARTMENTS as $department) {
            foreach (array_keys(self::stagesForDepartment($department)) as $stage) {
                $stages[] = $stage;
            }
        }

        return $stages;
    }

    /**
     * Process stage a role performs (e.g. checker_quality → quality_check).
     * Returns null for roles that do not own a production stage.
     */
    public static function stageForRole(?string $role): ?string
    {
        if (! $role) {
            return null;
        }

        foreach (self::STAGES as $stages) {
            foreach ($stages as $stage => $owner) {
                if ($owner === $role) {
                    return $stage;
                }
            }
        }

        return null;
    }

    /**
     * Fabric status required before a given stage may be assigned, or null
     * when the stage has no fabric-status prerequisite.
     */
    public static function requiredFabricStatusForStage(?string $stage): ?string
    {
        if (! $stage) {
            return null;
        }

        return self::STAGE_REQUIRES_FABRIC_STATUS[$stage] ?? null;
    }

    public static function label(?string $role): string
    {
        if (! $role) {
            return '';
        }

        return self::LABELS[$role] ?? ucwords(str_replace('_', ' ', $role));
    }

    public static function departmentLabel(?string $department): string
    {
        if (! $department) {
            return '';
        }

        return self::DEPARTMENT_LABELS[$department] ?? ucwords(str_replace('_', ' ', $department));
    }
}
