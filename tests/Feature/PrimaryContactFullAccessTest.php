<?php

namespace Tests\Feature;

use App\Models\Crm\Client;
use App\Models\Crm\CrmContact;
use App\Support\ClientPortal;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The primary contact of a business partner is its "main account": it always
 * holds full access to every page in the partner portal, and may open the
 * primary-only management pages. Non-primary representatives stay restricted
 * to the pages the primary granted them.
 */
class PrimaryContactFullAccessTest extends TestCase
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
            $table->unsignedBigInteger('lead_id')->nullable();
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

        Route::middleware('web')->group(function () {
            Route::middleware(['auth:client', 'client.access'])->get('partner/dashboard', fn () => 'DASH')->name('client.dashboard');
            Route::middleware(['auth:client', 'client.access'])->get('partner/products', fn () => 'PRODUCTS')->name('client.products');
            Route::middleware(['auth:client', 'client.access'])->get('partner/representatives', fn () => 'REPS')->name('client.representatives');
            Route::middleware(['auth:client', 'client.access'])->get('partner/access-control', fn () => 'AC')->name('client.access-control');
        });
    }

    protected function makeRep(array $overrides = []): array
    {
        $client = Client::create([
            'company_name' => 'Full Co',
            'email' => 'owner@full.test',
            'password' => Hash::make('secret'),
            'status' => 'active',
        ]);

        $rep = CrmContact::create(array_merge([
            'client_id'    => $client->id,
            'name'         => 'Rep One',
            'email'        => 'rep@full.test',
            'status'       => 'active',
            'has_account'  => true,
            'password'     => Hash::make('secret'),
            'access_pages' => ['dashboard'],
            'origin'       => 'partner',
        ], $overrides));

        return [$client, $rep];
    }

    public function test_primary_contact_is_auto_granted_every_page(): void
    {
        [, $rep] = $this->makeRep(['access_pages' => ['dashboard']]);

        $rep->update(['is_primary' => true]);

        $this->assertSame(ClientPortal::keys(), $rep->fresh()->access_pages);
    }

    public function test_non_primary_contact_pages_are_left_untouched(): void
    {
        [, $rep] = $this->makeRep(['access_pages' => ['dashboard']]);

        $this->assertSame(['dashboard'], $rep->fresh()->access_pages);
    }

    public function test_primary_representative_bypasses_page_restrictions(): void
    {
        [$client, $rep] = $this->makeRep();

        // Force primary with an empty grant (bypassing the model hook) so the
        // middleware's own full-access branch is what is being exercised.
        CrmContact::withoutEvents(fn () => $rep->forceFill(['is_primary' => true, 'access_pages' => []])->save());

        $this->actingAs($client, 'client')
            ->withSession(['representative_id' => $rep->id])
            ->get('partner/products')
            ->assertOk();
    }

    public function test_primary_representative_can_open_management_pages(): void
    {
        [$client, $rep] = $this->makeRep(['is_primary' => true]);

        $this->actingAs($client, 'client')
            ->withSession(['representative_id' => $rep->id])
            ->get('partner/representatives')
            ->assertOk();

        $this->actingAs($client, 'client')
            ->withSession(['representative_id' => $rep->id])
            ->get('partner/access-control')
            ->assertOk();
    }

    public function test_non_primary_representative_cannot_open_management_pages(): void
    {
        [$client, $rep] = $this->makeRep(['is_primary' => false]);

        $this->actingAs($client, 'client')
            ->withSession(['representative_id' => $rep->id])
            ->get('partner/representatives')
            ->assertStatus(403);
    }

    public function test_owner_keeps_management_when_no_primary_is_designated(): void
    {
        [$client] = $this->makeRep(['is_primary' => false]);

        $this->actingAs($client, 'client')
            ->get('partner/representatives')
            ->assertOk();
    }

    public function test_owner_matching_the_primary_contact_can_manage(): void
    {
        [$client] = $this->makeRep(['email' => 'owner@full.test', 'is_primary' => true]);

        $this->actingAs($client, 'client')
            ->get('partner/access-control')
            ->assertOk();
    }

    public function test_owner_keeps_management_when_another_contact_is_primary(): void
    {
        // The account-owner login remains the account administrator even when a
        // different CRM contact is flagged as the primary representative.
        [$client] = $this->makeRep(['email' => 'someone-else@full.test', 'is_primary' => true]);

        $this->actingAs($client, 'client')
            ->get('partner/representatives')
            ->assertOk();
    }

    public function test_owner_is_not_restricted_by_contact_page_grants(): void
    {
        // Contact-level grants apply to delegated representatives only; the
        // company-owner login always has the full portal.
        [$client] = $this->makeRep([
            'email'        => 'owner@full.test',
            'is_primary'   => false,
            'access_pages' => ['dashboard', 'orders'],
        ]);

        $this->actingAs($client, 'client')->get('partner/dashboard')->assertOk();
        $this->actingAs($client, 'client')->get('partner/products')->assertOk();
        $this->actingAs($client, 'client')->get('partner/representatives')->assertOk();
    }
}
