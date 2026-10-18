<?php

namespace Tests\Feature;

use App\Http\Controllers\Client\ClientPasswordController;
use App\Models\Crm\Client;
use App\Models\Crm\CrmContact;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The partner portal's "Change Password" action must work even when the
 * Profile page has been removed from a representative's granted pages, and it
 * must target the right account:
 *   - primary owner  → the clients row,
 *   - representative → their own crm_contacts row.
 */
class RepresentativePasswordChangeTest extends TestCase
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

        // Override the app's route with the real controller so the test is
        // self-contained (POST is not CSRF-checked while running tests).
        Route::middleware(['web', 'auth:client', 'client.access'])
            ->post('partner/password', [ClientPasswordController::class, 'update'])
            ->name('client.password.update');
    }

    protected function makeAccount(array $repOverrides = [], bool $withRep = true): array
    {
        $client = Client::create([
            'company_name' => 'Password Co',
            'email' => 'owner@password.test',
            'password' => Hash::make('secret'),
            'status' => 'active',
        ]);

        $rep = null;
        if ($withRep) {
            $rep = CrmContact::create(array_merge([
                'client_id' => $client->id,
                'name' => 'Rep One',
                'email' => 'rep@password.test',
                'status' => 'active',
                'has_account' => true,
                'password' => Hash::make('secret'),
                'access_pages' => ['dashboard'],
                'origin' => 'partner',
            ], $repOverrides));
        }

        return [$client, $rep];
    }

    public function test_primary_owner_can_change_their_password(): void
    {
        [$client] = $this->makeAccount(withRep: false);

        $this->actingAs($client, 'client')
            ->post('partner/password', [
                'current_password' => 'secret',
                'password' => 'Str0ng-Pass!',
                'password_confirmation' => 'Str0ng-Pass!',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('Str0ng-Pass!', $client->fresh()->password));
    }

    public function test_representative_without_profile_access_can_change_their_own_password(): void
    {
        [$client, $rep] = $this->makeAccount(['access_pages' => ['dashboard']]);

        $this->actingAs($client, 'client')
            ->withSession(['representative_id' => $rep->id])
            ->post('partner/password', [
                'current_password' => 'secret',
                'password' => 'Str0ng-Pass!',
                'password_confirmation' => 'Str0ng-Pass!',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('Str0ng-Pass!', $rep->fresh()->password));
        // The owner account is untouched.
        $this->assertTrue(Hash::check('secret', $client->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        [$client] = $this->makeAccount(withRep: false);

        $this->actingAs($client, 'client')
            ->post('partner/password', [
                'current_password' => 'wrong',
                'password' => 'Str0ng-Pass!',
                'password_confirmation' => 'Str0ng-Pass!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('secret', $client->fresh()->password));
    }
}
