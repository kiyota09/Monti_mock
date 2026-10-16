<?php

namespace Tests\Feature;

use App\Models\Crm\Client;
use App\Support\RouteHash;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientProfileHashTest extends TestCase
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

        // Probe using the same implicit {client} binding brands of routes use.
        Route::middleware(['web', 'auth:client'])->group(function () {
            Route::get('probe-client/{client}', fn (Client $client) => 'ok:' . $client->id);
        });
    }

    public function test_keys_hide_the_numeric_id_and_round_trip(): void
    {
        foreach ([1, 2, 47] as $id) {
            $key = RouteHash::encode($id, 'client');

            $this->assertMatchesRegularExpression('/\A[A-Za-z0-9\-_]+\z/', $key);
            $this->assertNotSame((string) $id, $key);
            $this->assertSame($id, RouteHash::decode($key, 'client'));
            $this->assertNotEquals($key, RouteHash::encode($id + 1, 'client'));
        }
    }

    public function test_keys_are_context_bound(): void
    {
        $clientKey = RouteHash::encode(2, 'client');

        // A client key never resolves under another context and vice versa.
        $this->assertNull(RouteHash::decode($clientKey, 'inquiry'));
        $this->assertNull(RouteHash::decode($clientKey, 'other'));
        $this->assertNull(RouteHash::decode('2', 'client'));
        $this->assertNull(RouteHash::decode('', 'client'));
    }

    public function test_route_binding_accepts_hashed_keys_only(): void
    {
        $client = Client::create([
            'company_name' => 'Hash Co',
            'email' => 'hash.co@test.local',
            'password' => Hash::make('password123'),
        ]);

        $key = RouteHash::encode($client->id, 'client');

        // Model URL generation emits the hashed key, never the raw ID.
        $this->assertNotSame((string) $client->id, $client->getRouteKey());
        $this->assertSame($key, $client->getRouteKey());

        $this->actingAs($client, 'client')->get('probe-client/' . $key)
            ->assertStatus(200)
            ->assertSee('ok:' . $client->id);

        // Raw IDs and forged keys 404 (flip a payload char — the last
        // base64 char carries padding bits that may not affect decoding).
        $tampered = ($key[0] === 'A' ? 'B' : 'A') . substr($key, 1);
        $this->actingAs($client, 'client')->get('probe-client/' . $client->id)->assertStatus(404);
        $this->actingAs($client, 'client')->get('probe-client/' . $tampered)->assertStatus(404);
    }
}
