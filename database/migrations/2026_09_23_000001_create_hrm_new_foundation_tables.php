<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HRM_NEW foundation: DB-rooted roles, org departments, org positions,
     * employment types + FK hooks on users. Old users.role/position strings
     * are kept for BC; new *_id columns are the dynamic source of truth.
     */
    public function up(): void
    {
        // 1. Canonical roles (module access). Seeds ERP-wide module codes.
        Schema::create('hrm_roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // HRM, SCM, FIN, MAN, ...
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Org departments (HRM_NEW Department.vue contract)
        Schema::create('hrm_departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // DEP-001
            $table->string('name');
            $table->string('category', 60)->nullable(); // module category
            $table->text('description')->nullable();
            $table->foreignId('head_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active'); // active|inactive
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'archived_at']);
        });

        // 3. Org positions (HRM_NEW Position.vue contract).
        // NOTE: legacy `position_to_applicants` table stays untouched for BC.
        Schema::create('hrm_positions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // POS-001
            $table->string('name');
            $table->foreignId('department_id')->nullable()->constrained('hrm_departments')->nullOnDelete();
            $table->foreignId('reports_to_id')->nullable()->constrained('hrm_positions')->nullOnDelete();
            $table->string('management_level', 60)->default('Individual Contributor');
            $table->unsignedTinyInteger('organization_level')->default(3);
            $table->unsignedTinyInteger('rank')->nullable(); // 1-10
            $table->text('description')->nullable();
            $table->text('responsibilities')->nullable();
            $table->text('qualifications')->nullable();
            $table->text('required_skills')->nullable(); // CSV
            $table->text('required_training')->nullable();
            $table->text('required_certifications')->nullable();
            $table->text('required_equipment')->nullable();
            $table->integer('salary_min')->nullable();
            $table->integer('salary_max')->nullable();
            $table->boolean('overtime_eligible')->default(false);
            $table->unsignedInteger('approved_headcount')->default(1);
            $table->string('status', 20)->default('active');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['department_id', 'status']);
        });

        // 4. Employment types (HRM_NEW EmploymentTypes.vue contract)
        Schema::create('hrm_employment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        // 5. FK hooks on users (nullable for BC rollout)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('hrm_role_id')->nullable()->after('role')->constrained('hrm_roles')->nullOnDelete();
            $table->foreignId('hrm_department_id')->nullable()->after('hrm_role_id')->constrained('hrm_departments')->nullOnDelete();
            $table->foreignId('hrm_position_id')->nullable()->after('hrm_department_id')->constrained('hrm_positions')->nullOnDelete();
            $table->foreignId('hrm_employment_type_id')->nullable()->after('hrm_position_id')->constrained('hrm_employment_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hrm_employment_type_id');
            $table->dropConstrainedForeignId('hrm_position_id');
            $table->dropConstrainedForeignId('hrm_department_id');
            $table->dropConstrainedForeignId('hrm_role_id');
        });
        Schema::dropIfExists('hrm_employment_types');
        Schema::dropIfExists('hrm_positions');
        Schema::dropIfExists('hrm_departments');
        Schema::dropIfExists('hrm_roles');
    }
};
