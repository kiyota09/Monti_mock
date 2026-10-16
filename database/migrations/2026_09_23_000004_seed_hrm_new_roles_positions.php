<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Root required roles + positions + departments in DB so the whole ERP
     * stays functional after HRM_NEW replaces old HRM.
     * Idempotent: uses firstOrCreate-style checks. Also grants new HRM pages
     * to existing HRM managers (edit) to avoid lockout.
     */
    public function up(): void
    {
        $now = now();

        // 1. Roles = ERP module codes (must match users.role enum + AccessGate)
        $roles = [
            ['HRM', 'Human Resources', 'Hires, onboards, pays workforce'],
            ['SCM', 'Supply Chain', 'Procurement & planning'],
            ['FIN', 'Finance', 'Receivables, payables, payroll approval'],
            ['MAN', 'Manufacturing', 'Plant operations'],
            ['INV', 'Inventory', 'Materials & product master'],
            ['ORD', 'Orders', 'Order processing'],
            ['WAR', 'Warehouse', 'Receiving & storage'],
            ['CRM', 'CRM', 'Leads & customers'],
            ['ECO', 'E-Commerce', 'Store & inquiries'],
            ['LOG', 'Logistics', 'Fleet & delivery'],
            ['PRO', 'Procurement', 'Vendor gateway'],
            ['PROJ', 'Projects', 'Project automation'],
            ['IT', 'IT', 'Systems admin'],
            ['WRF', 'Workforce', 'Scheduling addon'],
            ['CEO', 'President', 'Executive overseer'],
            ['COO', 'Vice President', 'Execution arm'],
        ];
        foreach ($roles as [$code, $name, $desc]) {
            if (! DB::table('hrm_roles')->where('code', $code)->exists()) {
                DB::table('hrm_roles')->insert([
                    'code' => $code, 'name' => $name, 'description' => $desc,
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // 2. Departments (align with ERP modules + plant)
        $departments = [
            ['DEP-HRM', 'Human Resources', 'HRM'],
            ['DEP-SCM', 'Supply Chain', 'SCM'],
            ['DEP-FIN', 'Finance', 'FIN'],
            ['DEP-MAN', 'Manufacturing', 'MAN'],
            ['DEP-INV', 'Inventory', 'INV'],
            ['DEP-ORD', 'Order Processing', 'ORD'],
            ['DEP-WAR', 'Warehouse', 'WAR'],
            ['DEP-CRM', 'Customer Relations', 'CRM'],
            ['DEP-ECO', 'E-Commerce', 'ECO'],
            ['DEP-LOG', 'Logistics', 'LOG'],
            ['DEP-PRO', 'Procurement', 'PRO'],
            ['DEP-IT', 'IT & Systems', 'IT'],
        ];
        foreach ($departments as [$code, $name, $cat]) {
            if (! DB::table('hrm_departments')->where('code', $code)->exists()) {
                DB::table('hrm_departments')->insert([
                    'code' => $code, 'name' => $name, 'category' => $cat,
                    'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // 3. Employment types
        foreach (['Full-time', 'Part-time', 'Contract', 'Temporary', 'Internship', 'Probationary'] as $name) {
            $slug = Str::slug($name);
            if (! DB::table('hrm_employment_types')->where('slug', $slug)->exists()) {
                DB::table('hrm_employment_types')->insert([
                    'name' => $name, 'slug' => $slug, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // 4. Core org positions
        $hrmDept = DB::table('hrm_departments')->where('code', 'DEP-HRM')->value('id');
        $positions = [
            ['POS-001', 'HR Manager', 2, 'Managerial'],
            ['POS-002', 'HR Staff', 3, 'Individual Contributor'],
            ['POS-003', 'Recruiter', 3, 'Individual Contributor'],
            ['POS-004', 'Payroll Officer', 3, 'Individual Contributor'],
            ['POS-005', 'Company Secretary', 2, 'Managerial'],
            ['POS-006', 'Special Officer (GM)', 1, 'Executive'],
            ['POS-007', 'Supervisor', 3, 'Supervisory'],
            ['POS-008', 'Trainee', 5, 'Trainee'],
        ];
        foreach ($positions as [$code, $name, $level, $mlevel]) {
            if (! DB::table('hrm_positions')->where('code', $code)->exists()) {
                DB::table('hrm_positions')->insert([
                    'code' => $code, 'name' => $name, 'department_id' => $hrmDept,
                    'management_level' => $mlevel, 'organization_level' => $level,
                    'approved_headcount' => 5,
                    'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // 5. Backfill users FKs from legacy strings (best-effort, nullable)
        $roleMap = DB::table('hrm_roles')->pluck('id', 'code');
        $deptMap = DB::table('hrm_departments')->pluck('id', 'category');
        foreach ($roleMap as $code => $id) {
            DB::table('users')->where('role', $code)->whereNull('hrm_role_id')->update(['hrm_role_id' => $id]);
        }
        foreach ($deptMap as $cat => $id) {
            DB::table('users')->where('role', $cat)->whereNull('hrm_department_id')->update(['hrm_department_id' => $id]);
        }

        // 6. Default onboarding template
        if (! DB::table('hrm_onboarding_templates')->where('code', 'STD-001')->exists()) {
            DB::table('hrm_onboarding_templates')->insert([
                'code' => 'STD-001', 'name' => 'Standard Employee Onboarding',
                'description' => 'Default checklist for regular hires',
                'status' => 'Active', 'version' => 1, 'is_default' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 7. Grant new HRM pages to existing HRM managers + secretaries (edit)
        // Keeps system functional: no lockout after module_pages extension.
        $newPages = ['workforce', 'recruitment', 'onboarding_templates', 'organization', 'performance', 'training', 'attendance', 'leave', 'archive'];
        $userIds = DB::table('users')
            ->where('role', 'HRM')->where('position', 'manager')
            ->orWhere(function ($q) {
                $q->where('position', 'secretary')->orWhere('position', 'special_officer');
            })->pluck('id');
        foreach ($userIds as $uid) {
            foreach ($newPages as $page) {
                $exists = DB::table('page_permissions')
                    ->where('user_id', $uid)->where('module', 'HRM')->where('page', $page)->exists();
                if (! $exists) {
                    DB::table('page_permissions')->insert([
                        'user_id' => $uid, 'module' => 'HRM', 'page' => $page,
                        'permission_level' => 'edit', 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $newPages = ['workforce', 'recruitment', 'onboarding_templates', 'organization', 'performance', 'training', 'attendance', 'leave', 'archive'];
        DB::table('page_permissions')->where('module', 'HRM')->whereIn('page', $newPages)->delete();
        DB::table('hrm_onboarding_templates')->where('code', 'STD-001')->delete();
        // Keep roles/departments/positions (organizational data) on rollback.
    }
};
