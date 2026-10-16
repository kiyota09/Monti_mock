<?php

namespace Tests\Feature;

use App\Models\Core\User;
use App\Models\Eco\Inquiry;
use App\Support\InquiryHash;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InquiryHashTest extends TestCase
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
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->text('initial_message')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('last_message_at')->nullable();
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
            $table->timestamps();
        });
        Schema::create('workforce_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->timestamps();
        });

        // Probe using the same implicit {inquiry} binding as the CRM and
        // client conversation routes (web group provides SubstituteBindings).
        Route::middleware(['web', 'auth'])->group(function () {
            Route::get('probe-inquiry/{inquiry}', fn (Inquiry $inquiry) => 'ok:' . $inquiry->id);
        });
    }

    public function test_keys_hide_the_numeric_id_and_round_trip(): void
    {
        foreach ([1, 2, 47, 999999] as $id) {
            $key = InquiryHash::encode($id);

            // Opaque: short, URL-safe, never the bare number, and with no
            // sequential relationship ( neighbouring IDs share no prefix).
            $this->assertMatchesRegularExpression('/\A[A-Za-z0-9\-_]+\z/', $key);
            $this->assertNotSame((string) $id, $key);
            $this->assertSame($id, InquiryHash::decode($key));

            // Distinct IDs never share a key.
            $this->assertNotEquals($key, InquiryHash::encode($id + 1));
        }
    }

    public function test_raw_numeric_and_forged_keys_are_rejected(): void
    {
        $this->assertNull(InquiryHash::decode('2'));
        $this->assertNull(InquiryHash::decode(''));
        $this->assertNull(InquiryHash::decode('not-a-key!!'));

        $key = InquiryHash::encode(2);
        $tampered = substr($key, 0, -1) . (substr($key, -1) === 'A' ? 'B' : 'A');
        $this->assertNull(InquiryHash::decode($tampered));
    }

    public function test_route_binding_accepts_hashed_keys_only(): void
    {
        $user = User::create([
            'name' => 'Hash Probe',
            'email' => 'hash.probe@test.local',
            'password' => Hash::make('password123'),
            'role' => 'SCM',
            'position' => 'staff',
            'is_active' => true,
        ]);
        $inquiry = Inquiry::create(['status' => 'open']);

        $key = InquiryHash::encode($inquiry->id);

        // Model URL generation emits the hashed key, never the raw ID.
        $this->assertStringNotContainsString((string) $inquiry->id, $inquiry->getRouteKey());
        $this->assertSame($key, $inquiry->getRouteKey());

        $this->actingAs($user)->get('probe-inquiry/' . $key)
            ->assertStatus(200)
            ->assertSee('ok:' . $inquiry->id);

        // Raw IDs and forged keys 404.
        $this->actingAs($user)->get('probe-inquiry/' . $inquiry->id)->assertStatus(404);
        $this->actingAs($user)->get('probe-inquiry/' . substr($key, 0, -1) . 'x')->assertStatus(404);
    }
}
