<?php

namespace Tests\Feature;

use App\Models\Core\PagePermission;
use App\Models\Core\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinAccessRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('FIN');
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
        // Finance ledger tables (FIN pages read live data, never dummies).
        Schema::create('fin_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->unique();
            $table->foreignId('sales_order_id')->nullable();
            $table->foreignId('client_id')->nullable();
            $table->string('client_name')->default('N/A');
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status')->default('unpaid');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('fin_invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fin_invoice_id');
            $table->decimal('amount', 15, 2);
            $table->date('paid_at');
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('recorded_by')->nullable();
            $table->timestamps();
        });
        Schema::create('fin_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_no')->unique();
            $table->foreignId('supplier_id')->nullable();
            $table->string('supplier_name')->default('N/A');
            $table->string('category')->default('Materials');
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status')->default('unpaid');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('fin_bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fin_bill_id');
            $table->decimal('amount', 15, 2);
            $table->date('paid_at');
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('recorded_by')->nullable();
            $table->timestamps();
        });
        Schema::create('fin_expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date');
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('department')->nullable();
            $table->foreignId('recorded_by')->nullable();
            $table->timestamps();
        });
        Schema::create('fin_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('department');
            $table->string('period', 7);
            $table->decimal('allocated', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id');
            $table->decimal('gross_pay', 15, 2)->default(0);
            $table->decimal('net_pay', 15, 2)->default(0);
            $table->decimal('sss_deduction', 15, 2)->default(0);
            $table->decimal('philhealth_deduction', 15, 2)->default(0);
            $table->decimal('pagibig_deduction', 15, 2)->default(0);
            $table->decimal('tax_withheld', 15, 2)->default(0);
            $table->decimal('sss_loan', 15, 2)->default(0);
            $table->decimal('pf_loan', 15, 2)->default(0);
            $table->decimal('late_total_deduction', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        // Order sources for receivable auto-sync (empty here — sync is a no-op).
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->default('N/A');
            $table->timestamps();
        });
        // Supplier purchase invoices feed the payables sync (empty here).
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('unpaid');
            $table->timestamps();
            $table->softDeletes();
        });
        // Procurement collections feed the cash-flow stats (empty here).
        Schema::create('procurement_payments', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->date('paid_date')->nullable();
            $table->timestamps();
        });
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('jo_number')->nullable();
            $table->foreignId('client_id')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('payment_status')->default('unpaid');
            $table->foreignId('recipe_id')->nullable();
            $table->date('expected_ship_date')->nullable();
            $table->timestamps();
        });
    }

    private function makeFinStaff(): User
    {
        // NOTE: role/position/is_active are NOT mass-assignable on User —
        // persist via query and return fresh, or every module gate 403s.
        $staff = User::create([
            'name' => 'FIN Staff',
            'email' => 'fin.staff@test.local',
            'password' => Hash::make('password123'),
        ]);
        User::where('id', $staff->id)->update([
            'role' => 'FIN', 'position' => 'staff', 'is_active' => true,
        ]);

        return $staff->fresh();
    }

    public function test_fin_staff_can_open_granted_pages(): void
    {
        $staff = $this->makeFinStaff();
        PagePermission::create(['user_id' => $staff->id, 'module' => 'FIN', 'page' => 'dashboard', 'permission_level' => 'view']);
        PagePermission::create(['user_id' => $staff->id, 'module' => 'FIN', 'page' => 'receivables', 'permission_level' => 'view']);

        // Both granted pages open for staff (previously 403 via position:manager).
        $this->actingAs($staff)->get(route('fin.manager.dashboard'))->assertStatus(200);
        $this->actingAs($staff)->get(route('fin.manager.receivables'))->assertStatus(200);

        // Ungranted pages stay locked.
        $this->actingAs($staff)->get(route('fin.manager.payables'))->assertStatus(403);
    }

    public function test_dashboard_lands_on_first_granted_page_when_dashboard_not_granted(): void
    {
        $staff = $this->makeFinStaff();
        PagePermission::create(['user_id' => $staff->id, 'module' => 'FIN', 'page' => 'receivables', 'permission_level' => 'view']);
        PagePermission::create(['user_id' => $staff->id, 'module' => 'FIN', 'page' => 'payables', 'permission_level' => 'view']);

        // Native FIN staff landing (fin.employee.dashboard) would 403 without
        // a dashboard grant — the resolver must send them to receivables.
        $response = $this->actingAs($staff)->get(route('dashboard'));
        $response->assertRedirect(route('fin.manager.receivables'));
    }

    public function test_dashboard_shows_waiting_page_when_all_fin_pages_disabled(): void
    {
        $staff = $this->makeFinStaff();
        PagePermission::create(['user_id' => $staff->id, 'module' => 'FIN', 'page' => 'dashboard', 'permission_level' => 'disabled']);
        PagePermission::create(['user_id' => $staff->id, 'module' => 'FIN', 'page' => 'receivables', 'permission_level' => 'disabled']);

        $this->actingAs($staff)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/AwaitingAccess'));
    }

    public function test_stale_page_visit_redirects_to_waiting_page_when_all_disabled(): void
    {
        $staff = $this->makeFinStaff();
        PagePermission::create(['user_id' => $staff->id, 'module' => 'FIN', 'page' => 'dashboard', 'permission_level' => 'disabled']);
        PagePermission::create(['user_id' => $staff->id, 'module' => 'FIN', 'page' => 'receivables', 'permission_level' => 'disabled']);

        // Stale sidebar link / bookmark (e.g. open before IT disabled
        // everything) lands friendly instead of a raw 403.
        $this->actingAs($staff)->get(route('fin.manager.receivables'))
            ->assertRedirect(route('awaiting.access'));
    }

    public function test_forbidden_page_stays_403_when_user_holds_other_grants(): void
    {
        $staff = $this->makeFinStaff();
        PagePermission::create(['user_id' => $staff->id, 'module' => 'FIN', 'page' => 'dashboard', 'permission_level' => 'view']);
        PagePermission::create(['user_id' => $staff->id, 'module' => 'FIN', 'page' => 'receivables', 'permission_level' => 'view']);

        // Has access elsewhere → exact 403 is preserved (no redirect).
        $this->actingAs($staff)->get(route('fin.manager.payables'))->assertStatus(403);
    }
}
