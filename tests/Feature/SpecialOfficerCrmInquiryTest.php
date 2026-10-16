<?php

namespace Tests\Feature;

use App\Http\Controllers\It\ItAccessControlController;
use App\Models\Core\PagePermission;
use App\Models\Core\User;
use App\Models\Core\UserModuleAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpecialOfficerCrmInquiryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
        Schema::create('user_module_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('module');
            $table->string('permission_level')->nullable();
            $table->string('access_level')->nullable();
            $table->foreignId('granted_by')->nullable();
            $table->timestamps();
        });
        Schema::create('workforce_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->timestamps();
        });
        Schema::create('it_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable();
            $table->foreignId('target_user_id')->nullable();
            $table->string('action', 50);
            $table->text('details')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        // Probe routes stand in for the real CRM inquiry pages (same gates,
        // no controller tables needed). URIs live under dashboard/crm-* so
        // module detection resolves CRM.
        Route::middleware(['auth'])->group(function () {
            Route::get('dashboard/crm-probe/module', fn () => 'ok')->middleware('module.access:CRM');
            Route::get('dashboard/crm-probe/inquiry', fn () => 'ok')
                ->middleware(['module.access:CRM', 'page.permission:inquiry,view']);
        });
    }

    protected function makeUser(string $role, string $position): User
    {
        // NOTE: role/position/is_active are NOT mass-assignable on User —
        // persist via query and return fresh, or the gates misbehave.
        $user = User::create([
            'name' => $position . ' ' . $role,
            'email' => strtolower($position) . '.' . strtolower($role) . '@test.local',
            'password' => Hash::make('password123'),
        ]);
        User::where('id', $user->id)->update([
            'role' => $role, 'position' => $position, 'is_active' => true,
        ]);

        return $user->fresh();
    }

    public function test_special_officer_with_module_and_page_grant_opens_inquiry(): void
    {
        $officer = $this->makeUser('SCM', 'special_officer');

        // No grants yet → locked out of the foreign module.
        $this->actingAs($officer)->get('dashboard/crm-probe/module')->assertStatus(403);
        $this->actingAs($officer)->get('dashboard/crm-probe/inquiry')->assertStatus(403);

        UserModuleAccess::create(['user_id' => $officer->id, 'module' => 'CRM']);
        PagePermission::create([
            'user_id' => $officer->id, 'module' => 'CRM',
            'page' => 'inquiry', 'permission_level' => 'view',
        ]);

        $this->actingAs($officer)->get('dashboard/crm-probe/module')->assertStatus(200);
        $this->actingAs($officer)->get('dashboard/crm-probe/inquiry')->assertStatus(200);
        $this->assertTrue($officer->hasPagePermission('CRM', 'inquiry', 'view'));
    }

    public function test_special_officer_with_page_grant_only_still_enters_module_shell(): void
    {
        // Regression: the module gate used to 403 special_officer accounts
        // holding only a per-page grant (no user_module_access row), so the
        // sidebar item showed but the page would not open. Usable page
        // grants now open the shell — page.permission still gates the page.
        $officer = $this->makeUser('SCM', 'special_officer');

        PagePermission::create([
            'user_id' => $officer->id, 'module' => 'CRM',
            'page' => 'inquiry', 'permission_level' => 'edit',
        ]);

        $this->actingAs($officer)->get('dashboard/crm-probe/module')->assertStatus(200);
        $this->actingAs($officer)->get('dashboard/crm-probe/inquiry')->assertStatus(200);
    }

    public function test_update_pages_provisions_module_shell_for_elevated_grant(): void
    {
        // Regression: IT setting a CRM page for a special_officer without
        // first saving the Modules tab silently dropped the row (module not
        // in allowedModules). The Pages save now provisions the module shell.
        $actor = $this->makeUser('IT', 'manager');
        $officer = $this->makeUser('SCM', 'special_officer');
        $this->actingAs($actor);

        $controller = new ItAccessControlController;
        $request = Request::create('/dashboard/it/access-control/pages', 'POST', [
            'user_id' => $officer->id,
            'grants' => [
                ['module' => 'SCM', 'page' => 'dashboard', 'level' => 'edit'],
                ['module' => 'CRM', 'page' => 'inquiry', 'level' => 'view'],
            ],
        ]);

        $controller->updatePages($request);

        $this->assertDatabaseHas('page_permissions', [
            'user_id' => $officer->id, 'module' => 'CRM',
            'page' => 'inquiry', 'permission_level' => 'view',
        ]);
        $this->assertDatabaseHas('user_module_access', [
            'user_id' => $officer->id, 'module' => 'CRM',
        ]);

        // And the officer can now open the page through both gates.
        $this->actingAs($officer)->get('dashboard/crm-probe/module')->assertStatus(200);
        $this->actingAs($officer)->get('dashboard/crm-probe/inquiry')->assertStatus(200);
    }
}
