<?php

namespace Tests\Feature;

use App\Models\Crm\Client;
use App\Models\Crm\CrmContact;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression: a business-partner representative whose page access was removed
 * used to bounce in an infinite browser redirect:
 *
 *   /partner/<page>  →403→  /awaiting-access  →302→  /login
 *                    →302→  /partner/dashboard →403→  ...
 *
 * `auth:client` makes the resolved guard `client`, so the global 403 handler's
 * `$request->user()` was the Client; AccessGate reported "no access" and
 * redirected to the EMPLOYEE awaiting-access page, which fed back through the
 * employee login (guest:web,client → client.dashboard). Portal 403s must now
 * pass through untouched.
 */
class RepresentativeAccessLoopTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('status')->default('active');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('name')->nullable();
            $table->string('title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_decision_maker')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->boolean('has_account')->default(false);
            $table->string('password')->nullable();
            $table->json('access_pages')->nullable();
            $table->string('origin')->default('crm');
            $table->timestamp('account_created_at')->nullable();
            $table->timestamps();
        });

        // Tables touched by AccessGate when an employee 403 is evaluated.
        Schema::create('page_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('module')->nullable();
            $table->string('page')->nullable();
            $table->string('permission_level')->nullable();
            $table->timestamps();
        });
        Schema::create('workforce_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
        Schema::create('user_module_access', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('module')->nullable();
            $table->timestamps();
        });

        Route::middleware('web')->group(function () {
            Route::middleware('guest:client')->get('client/login', fn () => 'LOGIN')->name('client.login');
            Route::get('dashboard', fn () => 'EMP')->name('dashboard');

            Route::middleware(['auth:client', 'client.access'])->get('partner/dashboard', fn () => 'DASH')->name('client.dashboard');
            Route::middleware(['auth:client', 'client.access'])->get('partner/profile', fn () => 'PROFILE')->name('client.profile.edit');
        });
    }

    protected function makeRep(array $overrides = []): array
    {
        $client = Client::create([
            'company_name' => 'Loop Co',
            'email' => 'owner@loop.test',
            'password' => Hash::make('secret'),
            'status' => 'active',
        ]);

        $rep = CrmContact::create(array_merge([
            'client_id' => $client->id,
            'name' => 'Rep One',
            'email' => 'rep@loop.test',
            'status' => 'active',
            'has_account' => true,
            'password' => Hash::make('secret'),
            'access_pages' => ['dashboard', 'profile'],
            'origin' => 'partner',
        ], $overrides));

        return [$client, $rep];
    }

    public function test_denied_page_returns_403_and_does_not_redirect(): void
    {
        [$client, $rep] = $this->makeRep(['access_pages' => ['dashboard']]);

        $response = $this->actingAs($client, 'client')
            ->withSession(['representative_id' => $rep->id])
            ->get('partner/profile');

        $response->assertStatus(403);
        $this->assertFalse($response->isRedirect(), 'A denied portal page must not redirect (it loops).');
    }

    public function test_granted_page_is_reachable(): void
    {
        [$client, $rep] = $this->makeRep(['access_pages' => ['dashboard']]);

        $this->actingAs($client, 'client')
            ->withSession(['representative_id' => $rep->id])
            ->get('partner/dashboard')
            ->assertOk();
    }

    public function test_revoked_representative_does_not_loop(): void
    {
        [$client, $rep] = $this->makeRep(['status' => 'rejected', 'has_account' => false]);

        $response = $this->actingAs($client, 'client')
            ->withSession(['representative_id' => $rep->id])
            ->get('partner/dashboard');

        $chain = [];
        $hops = 0;
        while ($response->isRedirect() && $hops < 6) {
            $target = $response->headers->get('Location');
            $chain[] = $response->getStatusCode().' -> '.$target;
            $response = $this->get($target);
            $hops++;
        }

        $this->assertLessThan(6, $hops, 'Redirect loop detected: '.implode(' | ', $chain));
    }
}
