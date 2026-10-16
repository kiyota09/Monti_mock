<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employee Master Record fields on users. All nullable for BC;
     * names backfilled from the existing `name` column.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('suffix')->nullable();
            $table->string('nickname')->nullable();
            $table->string('preferred_name')->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('civil_status', 20)->nullable();
            $table->string('nationality', 60)->nullable();
            $table->string('personal_email')->nullable();
            $table->string('mobile_number', 40)->nullable();
            $table->string('telephone', 40)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_number', 40)->nullable();
            $table->text('current_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('country', 60)->nullable();
            $table->string('province', 60)->nullable();
            $table->string('city', 60)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('company')->nullable();
            $table->string('branch')->nullable();
            $table->string('business_unit')->nullable();
            $table->string('cost_center', 60)->nullable();
            $table->string('work_location')->nullable();
            $table->string('department_head')->nullable();
            $table->string('immediate_supervisor')->nullable();
            $table->string('payroll_group', 60)->nullable();
            $table->string('salary_grade', 30)->nullable();
            $table->decimal('basic_salary', 12, 2)->nullable();
            $table->string('bank_account')->nullable();
            $table->string('tax_id', 60)->nullable();
            $table->string('sss_id', 60)->nullable();
            $table->string('work_schedule')->nullable();
            $table->string('shift')->nullable();
            $table->string('timezone', 20)->nullable()->default('GMT+8');
            $table->string('biometrics_id', 60)->nullable();
            $table->date('probation_end_date')->nullable();
            $table->date('regularization_date')->nullable();
            $table->date('resignation_date')->nullable();
            $table->date('last_working_day')->nullable();
            $table->text('education')->nullable();
            $table->text('certifications')->nullable();
            $table->text('skills')->nullable();
            $table->string('languages')->nullable();
            $table->text('trainings')->nullable();
        });

        // Backfill names from `name` ("First Middle Last" → first / middle / last).
        foreach (DB::table('users')->select('id', 'name', 'first_name', 'last_name')->get() as $u) {
            if ($u->first_name && $u->last_name) {
                continue;
            }
            $parts = preg_split('/\s+/', trim((string) $u->name), -1, PREG_SPLIT_NO_EMPTY);
            $first = array_shift($parts) ?: $u->name;
            $last = $parts ? implode(' ', $parts) : $first;
            DB::table('users')->where('id', $u->id)->update([
                'first_name' => $u->first_name ?: $first,
                'last_name' => $u->last_name ?: $last,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'middle_name', 'last_name', 'suffix', 'nickname', 'preferred_name',
                'gender', 'date_of_birth', 'civil_status', 'nationality', 'personal_email',
                'mobile_number', 'telephone', 'emergency_contact_name', 'emergency_contact_number',
                'current_address', 'permanent_address', 'country', 'province', 'city', 'postal_code',
                'company', 'branch', 'business_unit', 'cost_center', 'work_location',
                'department_head', 'immediate_supervisor', 'payroll_group', 'salary_grade',
                'basic_salary', 'bank_account', 'tax_id', 'sss_id', 'work_schedule', 'shift',
                'timezone', 'biometrics_id', 'probation_end_date', 'regularization_date',
                'resignation_date', 'last_working_day', 'education', 'certifications',
                'skills', 'languages', 'trainings',
            ]);
        });
    }
};
