<?php

namespace Tests\Feature;

use App\Models\Core\PagePermission;
use App\Models\Core\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class ItPagePermissionRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Polyfill MySQL FIELD() for sqlite (used in priority ordering).
        DB::connection()->getPdo()->sqliteCreateFunction('FIELD', function ($value, ...$args) {
            $i = array_search($value, $args, true);
            return $i === false ? count($args) + 1 : $i + 1;
        }, -1);

        // Minimal schema (sqlite :memory: cannot run the MySQL migrations).
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('HRM');
            $table->string('position')->default('staff');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('page_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('module');
            $table->string('page');
            $table->string('permission_level')->nullable();
            $table->timestamps();
        });
        Schema::create('workforce_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->timestamps();
        });
        Schema::create('user_module_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('module');
            $table->timestamps();
        });
        // Lean IT tables (columns touched by the controllers under test).
        Schema::create('it_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no')->unique()->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('category')->default('incident');
            $table->string('system_area')->default('erp');
            $table->string('priority')->default('P3');
            $table->string('status')->default('open');
            $table->foreignId('requester_id')->nullable();
            $table->string('requester_name')->nullable();
            $table->foreignId('assignee_id')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('it_ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id');
            $table->foreignId('user_id');
            $table->text('body')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });
        Schema::create('it_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique()->nullable();
            $table->string('name')->nullable();
            $table->string('category')->default('hardware');
            $table->string('type')->nullable();
            $table->string('status')->default('available');
            $table->foreignId('assigned_to_user_id')->nullable();
            $table->date('warranty_end')->nullable();
            $table->timestamps();
        });
        Schema::create('it_systems', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('system_type')->default('application');
            $table->string('status')->default('operational');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('it_system_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id');
            $table->string('status')->default('operational');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('it_knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('category')->default('general');
            $table->text('body')->nullable();
            $table->string('audience')->default('all');
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('views')->default(0);
            $table->foreignId('author_id')->nullable();
            $table->timestamps();
        });
        Schema::create('it_changes', function (Blueprint $table) {
            $table->id();
            $table->string('change_no')->unique()->nullable();
            $table->string('title')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    /**
     * role/position/is_active are NOT mass-assignable on User — persist via
     * query and return fresh, or every module gate misbehaves.
     */
    private function makeStaff(string $name, string $email, string $role, string $position): User
    {
        $staff = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password123'),
        ]);
        User::where('id', $staff->id)->update([
            'role' => $role, 'position' => $position, 'is_active' => true,
        ]);

        return $staff->fresh();
    }

    public function test_two_page_grant_actually_restricts_native_it_staff(): void
    {
        $staff = $this->makeStaff('Perm Regression Staff', 'perm.regression@test.local', 'IT', 'staff');

        // 1. No explicit rows: legacy full access.
        $this->actingAs($staff)->get(route('it.dashboard'))->assertStatus(200);
        $this->actingAs($staff)->get(route('it.tickets'))->assertStatus(200);
        $this->assertTrue($staff->hasPagePermission('IT', 'assets'));

        // 2. Grant exactly 2 pages (view-only), like IT Access Control does.
        PagePermission::create(['user_id' => $staff->id, 'module' => 'IT', 'page' => 'tickets', 'permission_level' => 'view']);
        PagePermission::create(['user_id' => $staff->id, 'module' => 'IT', 'page' => 'knowledge', 'permission_level' => 'view']);

        // Edit-gated probe: view-only must be blocked from edit endpoints.
        Route::middleware(['auth'])->get('dashboard/it-probe/tickets-edit', fn () => 'ok')->middleware('page.permission:tickets,edit');

        // 3. Granted pages still open…
        $ticketsResponse = $this->actingAs($staff)->get(route('it.tickets'));
        $ticketsResponse->assertStatus(200);
        $this->actingAs($staff)->get(route('it.knowledge'))->assertStatus(200);
        $this->actingAs($staff)->get('dashboard/it-probe/tickets-edit')->assertStatus(403);

        // 4b. Shared Inertia props carry the REAL levels (buttons hide on view-only).
        $ticketsResponse->assertInertia(fn (Assert $page) => $page
            ->where('auth.page_permissions.0.module', 'IT')
            ->where('auth.page_permissions.0.page', 'tickets')
            ->where('auth.page_permissions.0.permission_level', 'view'));

        // 4. …everything else is now locked (this was the reported bug).
        $this->actingAs($staff)->get(route('it.assets'))->assertStatus(403);
        $this->actingAs($staff)->get(route('it.monitoring'))->assertStatus(403);
        $this->actingAs($staff)->get(route('it.changes'))->assertStatus(403);
        $this->actingAs($staff)->get(route('it.dashboard'))->assertStatus(403);

        // 5. View ≠ edit: level enforcement through the model helper.
        $this->assertTrue($staff->hasPagePermission('IT', 'tickets', 'view'));
        $this->assertFalse($staff->hasPagePermission('IT', 'tickets', 'edit'));
        $this->assertFalse($staff->hasPagePermission('IT', 'assets', 'view'));
    }

    public function test_hrm_dashboard_plus_employee_grant_opens_exactly_those_pages(): void
    {
        // Probe routes stand in for real HRM pages (same middleware, no controller tables needed).
        // URIs live under dashboard/hrm-* so module detection resolves HRM.
        Route::middleware(['auth'])->group(function () {
            Route::get('dashboard/hrm-probe/employee-view', fn () => 'ok')->middleware('page.permission:employee,view');
            Route::get('dashboard/hrm-probe/employee-edit', fn () => 'ok')->middleware('page.permission:employee,edit');
            Route::get('dashboard/hrm-probe/payroll', fn () => 'ok')->middleware('page.permission:payroll,view');
        });

        $staff = $this->makeStaff('HRM Perm Staff', 'hrm.perm@test.local', 'HRM', 'staff');

        // Grants stored exactly the way IT Access Control writes them
        // (UPPER module, lowercase config keys).
        PagePermission::create(['user_id' => $staff->id, 'module' => 'HRM', 'page' => 'dashboard', 'permission_level' => 'view']);
        PagePermission::create(['user_id' => $staff->id, 'module' => 'HRM', 'page' => 'employee', 'permission_level' => 'edit']);

        // Granted pages open…
        $this->actingAs($staff)->get('dashboard/hrm-probe/employee-view')->assertStatus(200);
        $this->actingAs($staff)->get('dashboard/hrm-probe/employee-edit')->assertStatus(200);

        // …everything else 403s (previously the auto-pass let it all through).
        $this->actingAs($staff)->get('dashboard/hrm-probe/payroll')->assertStatus(403);

        // Model helper agrees.
        $this->assertTrue($staff->hasPagePermission('HRM', 'employee', 'edit'));
        $this->assertFalse($staff->hasPagePermission('HRM', 'payroll', 'view'));

        // Stale plural keys (the old config bug) grant nothing.
        PagePermission::where('user_id', $staff->id)->delete();
        PagePermission::create(['user_id' => $staff->id, 'module' => 'HRM', 'page' => 'employees', 'permission_level' => 'edit']);
        $this->actingAs($staff)->get('dashboard/hrm-probe/employee-view')->assertStatus(403);
    }
}
