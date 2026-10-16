<?php

namespace Tests\Feature;

use App\Models\Crm\Client;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PortalShareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        // Plain probe through the web group so HandleInertiaRequests::share()
        // runs exactly like on the real portal pages.
        Route::middleware(['web'])->get('probe-portal', fn () => 'ok');
    }

    public function test_client_portal_visit_does_not_crash_employee_sharing(): void
    {
        // Regression: share() ran employee-only logic (hrmDepartment, page
        // permissions, …) for ANY authenticated model, so every portal visit
        // died with RelationNotFoundException on the Client model.
        config(['auth.defaults.guard' => 'client']);

        $client = Client::create([
            'company_name' => 'Portal Co',
            'email' => 'portal@test.local',
            'password' => Hash::make('password123'),
        ]);

        $this->actingAs($client, 'client')->get('probe-portal')->assertStatus(200);
    }
}
