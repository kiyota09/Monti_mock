<?php

namespace Tests\Feature;

use App\Models\Crm\Client;
use App\Models\Crm\FabricSampleRequest;
use App\Models\Eco\Inquiry;
use App\Models\Inv\Product;
use App\Support\InquiryHash;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use App\Models\Core\User;
use App\Models\Core\PagePermission;
use App\Models\Man\PantoneReference;
use App\Models\Man\PantoneFabricFormula;

class FabricSampleLoopTest extends TestCase
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
            $table->string('manufacturing_role')->nullable();
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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link_route')->nullable();
            $table->unsignedBigInteger('memo_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
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
        Schema::create('crm_page_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('page')->nullable();
            $table->timestamps();
        });
        Schema::create('crm_client_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id');
            $table->foreignId('client_id');
            $table->timestamps();
        });
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
        // Read by HandleInertiaRequests to resolve the account's primary login.
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('active');
            $table->boolean('has_account')->default(false);
            $table->json('access_pages')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('sku')->nullable();
            $table->timestamps();
        });
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('url')->nullable();
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
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inquiry_id');
            $table->string('sender_type', 16)->default('eco');
            $table->text('message')->nullable();
            $table->json('meeting_data')->nullable();
            $table->boolean('is_system_event')->default(false);
            $table->boolean('visible_to_client')->default(true);
            $table->unsignedBigInteger('sample_request_id')->nullable();
            $table->timestamps();
        });
        Schema::create('eco_conversation_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_message_id');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->boolean('approved_by_client')->default(false);
            $table->boolean('is_po')->default(false);
            $table->timestamps();
        });
        Schema::create('fabric_sample_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedBigInteger('inquiry_id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('fabric_name');
            $table->text('color_description');
            $table->text('notes')->nullable();
            $table->text('adjustment_notes')->nullable();
            $table->string('urgency', 16)->default('normal');
            $table->string('status', 32)->default('requested');
            $table->json('formula')->nullable();
            $table->string('sample_image_path')->nullable();
            $table->unsignedBigInteger('recipe_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('formulated_by')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('request_method')->nullable();
            $table->string('carrier_name')->nullable();
            $table->string('client_reference_path')->nullable();
            $table->string('texture')->nullable();
            $table->integer('gsm')->nullable();
            $table->string('color_hex')->nullable();
            $table->string('color_image_path')->nullable();
            $table->string('delivery_courier')->nullable();
            $table->string('delivery_tracking_reference')->nullable();
            $table->string('delivery_group_code')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->timestamp('delivery_sent_at')->nullable();
            $table->timestamp('client_noted_at')->nullable();
            $table->timestamp('client_received_at')->nullable();
            $table->unsignedBigInteger('delivery_message_id')->nullable();
            $table->unsignedBigInteger('pantone_reference_id')->nullable();
            $table->json('pantone_colors')->nullable();
            $table->json('pantone_formula')->nullable();
            $table->timestamps();
        });
        Schema::create('bom_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('product_id');
            $table->string('yarn_type');
            $table->string('dye_color');
            $table->string('weave_design');
            $table->json('materials')->nullable();
            $table->unsignedBigInteger('fabric_sample_request_id')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'product_id']);
        });
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('mat_id')->nullable();
            $table->string('name')->nullable();
            $table->string('category')->nullable();
            $table->string('unit')->nullable();
            $table->timestamps();
        });
        Schema::create('eco_quotations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inquiry_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('quotation_number')->nullable();
            $table->string('status')->default('sent');
            $table->boolean('request_new_quote')->default(false);
            $table->timestamps();
        });
        Schema::create('eco_quotation_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eco_quotation_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('fabric_type_id')->nullable();
            $table->string('fabric_type')->nullable();
            $table->string('fabric');
            $table->string('design')->nullable();
            $table->string('color');
            $table->decimal('kilos', 10, 2)->default(0);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('fabric_sample_formulations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fabric_sample_request_id');
            $table->unsignedSmallInteger('version');
            $table->json('formula');
            $table->string('attachment_path');
            $table->string('attachment_name');
            $table->string('attachment_mime');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->string('status')->default('awaiting_crm');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->unsignedBigInteger('conversation_message_id')->nullable();
            $table->timestamps();
        });
        Schema::create('pantone_references', function (Blueprint $table) {
            $table->id(); $table->string('name')->unique(); $table->string('hex')->nullable();
            $table->json('combinations')->nullable(); $table->unsignedBigInteger('created_by')->nullable(); $table->timestamps();
        });
        Schema::create('pantone_fabric_formulas', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('pantone_reference_id');
            $table->unsignedBigInteger('yarn_material_id'); $table->json('combinations');
            $table->boolean('is_demo_data')->default(false); $table->timestamps();
        });
    }

    protected function seedConversation(): array
    {
        $client = Client::create([
            'company_name' => 'Loop Co',
            'email' => 'loop@test.local',
            'password' => Hash::make('password123'),
        ]);
        $product = Product::create(['name' => 'Cotton Twill', 'sku' => 'CT-001']);
        $inquiry = Inquiry::create([
            'client_id' => $client->id,
            'product_id' => $product->id,
            'status' => 'open',
        ]);

        return [$client, $product, $inquiry];
    }

    protected function crmStaff(): User
    {
        $staff = User::create([
            'name' => 'CRM Staff',
            'email' => 'crm.staff@test.local',
            'password' => Hash::make('password123'),
            'role' => 'CRM',
            'position' => 'staff',
            'is_active' => true,
        ]);

        return $this->withRole($staff, 'CRM', 'staff');
    }

    /**
     * role/position/is_active are NOT mass-assignable on User, so create()
     * silently drops them (NULL in memory breaks every module gate).
     * Persist them via query and return a fresh model.
     */
    protected function withRole(User $user, string $role, string $position, ?string $manufacturingRole = null): User
    {
        $data = ['role' => $role, 'position' => $position, 'is_active' => true];
        if ($manufacturingRole !== null) {
            $data['manufacturing_role'] = $manufacturingRole;
        }
        User::where('id', $user->id)->update($data);

        return $user->fresh();
    }

    public function test_client_pantone_request_round_trips_through_lab_and_client_approval(): void
    {
        Storage::fake('public');
        [$client, $product, $inquiry] = $this->seedConversation();
        $crm = $this->crmStaff();
        $chemist = User::create([
            'name' => 'Pantone Chemist', 'email' => 'pantone.chemist@test.local',
            'password' => Hash::make('password123'),
        ]);
        $chemist = $this->withRole($chemist, 'MAN', 'staff', 'dyeing_lab_chemist');
        PagePermission::create([
            'user_id' => $chemist->id, 'module' => 'MAN', 'page' => 'production', 'permission_level' => 'edit',
        ]);

        $yarn = $this->labMaterial('YRN-PANTONE', 'Cotton 30s', 'Yarn');
        $dye = $this->labMaterial('DYE-PANTONE', 'Blue RR', 'Dye');
        $reference = PantoneReference::create(['name' => '19-4052 TCX', 'hex' => '#0F4C81']);
        PantoneFabricFormula::create([
            'pantone_reference_id' => $reference->id, 'yarn_material_id' => $yarn,
            'combinations' => ['dye_stuffs' => [['material_id' => $dye, 'name' => 'Blue RR', 'amount' => 2.5]]],
        ]);
        $quotationId = DB::table('eco_quotations')->insertGetId([
            'inquiry_id' => $inquiry->id, 'client_id' => $client->id,
            'quotation_number' => 'ECO-QT-PANTONE', 'status' => 'accepted',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $quotationItemId = DB::table('eco_quotation_items')->insertGetId([
            'eco_quotation_id' => $quotationId, 'product_id' => $product->id,
            'fabric_type_id' => $yarn, 'fabric_type' => 'Cotton 30s',
            'fabric' => $product->name, 'design' => 'Jersey', 'color' => 'Dark Colors',
            'kilos' => 25, 'unit_price' => 120, 'price' => 3000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $key = InquiryHash::encode($inquiry->id);
        $this->actingAs($client, 'client')->post(route('client.conversation.sample-request', $key), [
            'request_method' => 'pantone',
            'fabric_designs' => [[
                'quotation_item_id' => $quotationItemId,
                'colors' => [[
                    'pantone_code' => '19-4052 TCX', 'pantone_color' => 'Classic Blue',
                    'texture' => 'Medium', 'gsm' => 180, 'reference_type' => 'hex', 'color_hex' => '#0F4C81',
                ]],
            ]],
        ])->assertRedirect();
        $sample = FabricSampleRequest::firstOrFail();
        $this->assertSame('client_submitted', $sample->status);
        $this->assertSame('Classic Blue (19-4052 TCX)', $sample->color_description);

        $this->actingAs($crm)->post(route('crm.inquiry.sample.pass-to-lab', [$key, $sample->id]))->assertRedirect();
        $this->actingAs($chemist)->patch(route('man.staff.dyeing-lab-chemist.sample.start', $sample->id))->assertRedirect();
        $this->actingAs($chemist)->patch(route('man.staff.dyeing-lab-chemist.sample.pantone', $sample->id), [
            'pantone_reference_id' => $reference->id, 'color_index' => 0, 'yarn_material_id' => $yarn,
        ])->assertRedirect();
        $sample->refresh();
        $this->assertSame($reference->id, $sample->pantone_colors[0]['pantone_reference_id']);

        $this->actingAs($chemist)->post(route('man.staff.dyeing-lab-chemist.sample.formulate', $sample->id), [
            'dyestuffs' => [['material_id' => $dye, 'name' => 'Blue RR', 'pct' => 2.5]],
            'yarns' => [['material_id' => $yarn, 'name' => 'Cotton 30s', 'qty' => 5]],
            'weave_design' => 'Jersey', 'sample_photo' => $this->fakeSwatchPhoto(),
        ])->assertRedirect();
        $sample->refresh();
        $this->assertSame(FabricSampleRequest::STATUS_AWAITING_CRM_REVIEW, $sample->status);

        $this->actingAs($crm)->post(route('crm.inquiry.sample.deliver', [$key, $sample->id]), [
            'delivery_courier' => 'Test Courier', 'delivery_tracking_reference' => 'PANTONE-TRACK-1',
        ])->assertRedirect();
        $this->actingAs($client, 'client')->post(route('client.sample.noted', $sample->id))->assertRedirect();
        $this->actingAs($client, 'client')->post(route('client.sample.received', $sample->id))->assertRedirect();
        $this->actingAs($client, 'client')->post(route('client.sample.approve', $sample->id))->assertRedirect();

        $sample->refresh();
        $this->assertSame(FabricSampleRequest::STATUS_APPROVED, $sample->status);
        $this->assertNotNull($sample->recipe_id);
        $this->assertDatabaseHas('bom_records', ['fabric_sample_request_id' => $sample->id]);
    }

    public function test_crm_request_stays_hidden_from_client(): void
    {
        [$client, $product, $inquiry] = $this->seedConversation();
        $staff = $this->crmStaff();
        $key = InquiryHash::encode($inquiry->id);

        // 1. Request a sample from inside the conversation.
        $this->actingAs($staff)
            ->post(route('crm.inquiry.sample-request', $key), [
                'product_id' => $product->id,
                'color_description' => 'Deep royal blue, slight red cast',
                'urgency' => 'high',
            ])
            ->assertStatus(302);

        $sample = FabricSampleRequest::firstOrFail();
        $this->assertSame(FabricSampleRequest::STATUS_REQUESTED, $sample->status);
        $this->assertSame('Deep royal blue, slight red cast', $sample->color_description);
        $this->assertDatabaseHas('conversation_messages', [
            'inquiry_id' => $inquiry->id,
            'sample_request_id' => $sample->id,
        ]);

        // Internal lab request stays invisible to the client.
        $requestMessage = $sample->messages()->firstOrFail();
        $this->assertFalse((bool) $requestMessage->visible_to_client);
        $feed = $this->actingAs($client, 'client')
            ->getJson(route('client.conversation.feed', $key) . '?after=0')
            ->assertStatus(200)
            ->json();
        $this->assertSame([], $feed['messages']);
    }

    public function test_client_can_approve_or_request_adjustment(): void
    {
        [$client, $product, $inquiry] = $this->seedConversation();
        $sample = FabricSampleRequest::create([
            'code' => 'FSR-TEST-00001',
            'inquiry_id' => $inquiry->id,
            'client_id' => $client->id,
            'product_id' => $product->id,
            'fabric_name' => $product->name,
            'color_description' => 'Deep royal blue',
            'status' => FabricSampleRequest::STATUS_FORWARDED,
            'client_received_at' => now(),
            'formula' => ['yarns' => []],
        ]);

        // Approve → client may now send a P.O.
        $this->actingAs($client, 'client')
            ->post(route('client.sample.approve', $sample->id))
            ->assertStatus(302);
        $this->assertSame(
            FabricSampleRequest::STATUS_APPROVED,
            $sample->fresh()->status
        );

        // Adjustment round → loops back for a new request.
        $sample->refresh();
        $sample->update(['status' => FabricSampleRequest::STATUS_FORWARDED]);
        $this->actingAs($client, 'client')
            ->post(route('client.sample.adjust', $sample->id), ['notes' => 'Make it deeper'])
            ->assertStatus(302);
        $this->assertSame(
            FabricSampleRequest::STATUS_ADJUSTMENT_REQUESTED,
            $sample->fresh()->status
        );
        // Notes land on the request so the chemist reads them on the shade page.
        $this->assertSame('Make it deeper', $sample->fresh()->adjustment_notes);
    }

    /**
     * Seed an inventory material for lab formulation payloads.
     */
    protected function labMaterial(string $matId, string $name, string $category): int
    {
        return \App\Models\Inv\Material::create([
            'mat_id' => $matId, 'name' => $name, 'category' => $category, 'unit' => 'kg',
        ])->id;
    }

    /**
     * A real 1x1 JPEG (no GD needed) so the `image` validation rule passes.
     */
    protected function fakeSwatchPhoto(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'swatch') . '.jpg';
        file_put_contents($path, base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAQEBAQAAAAAAAAAAARP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8AVQA//9k='
        ));

        return new UploadedFile($path, 'swatch.jpg', 'image/jpeg', null, true);
    }

    public function test_chemist_can_formulate_sample_with_photo_and_recipe(): void
    {
        Storage::fake('public');
        [$client, $product, $inquiry] = $this->seedConversation();

        $chemist = User::create([
            'name' => 'Lab Chemist',
            'email' => 'chemist@test.local',
            'password' => Hash::make('password123'),
            'role' => 'MAN',
            'position' => 'staff',
            'manufacturing_role' => 'dyeing_lab_chemist',
            'is_active' => true,
        ]);
        $chemist = $this->withRole($chemist, 'MAN', 'staff', 'dyeing_lab_chemist');
        \App\Models\Core\PagePermission::create([
            'user_id' => $chemist->id, 'module' => 'MAN',
            'page' => 'production', 'permission_level' => 'edit',
        ]);

        $sample = FabricSampleRequest::create([
            'code' => 'FSR-TEST-00002',
            'inquiry_id' => $inquiry->id,
            'client_id' => $client->id,
            'product_id' => $product->id,
            'fabric_name' => $product->name,
            'color_description' => 'Deep royal blue',
            'status' => FabricSampleRequest::STATUS_IN_PROGRESS,
        ]);

        $this->actingAs($chemist)
            ->post(route('man.staff.dyeing-lab-chemist.sample.formulate', $sample->id), [
                'dyestuffs' => [['material_id' => $this->labMaterial('DYE-1', 'Blue RR', 'Dye'), 'name' => 'Blue RR', 'pct' => 2.5]],
                'auxiliaries' => [['material_id' => $this->labMaterial('AUX-1', 'Salt', 'Supplies'), 'name' => 'Salt', 'gpl' => 30]],
                'yarns' => [['material_id' => $this->labMaterial('YRN-1', 'Cotton 30s', 'Yarn'), 'name' => 'Cotton 30s', 'qty' => 5]],
                'weave_design' => 'Twill 2/1',
                'sample_photo' => $this->fakeSwatchPhoto(),
            ])
            ->assertStatus(302);

        $sample->refresh();
        $this->assertSame(FabricSampleRequest::STATUS_AWAITING_CRM_REVIEW, $sample->status);
        $this->assertNull($sample->recipe_id, 'The sample recipe is committed after client approval.');
        $this->assertNotNull($sample->sample_image_path);
        Storage::disk('public')->assertExists($sample->sample_image_path);

        // The chemist submits the swatch to CRM for review; it remains hidden
        // from the client until CRM records its courier and tracking details.
        $response = $sample->messages()->firstOrFail();
        $this->assertFalse((bool) $response->visible_to_client);
        $this->assertFalse((bool) $response->is_system_event);
        $this->assertDatabaseHas('eco_conversation_attachments', [
            'conversation_message_id' => $response->id,
        ]);

        $feed = $this->actingAs($client, 'client')
            ->getJson(route('client.conversation.feed', InquiryHash::encode($inquiry->id)) . '?after=0')
            ->assertStatus(200)
            ->json();
        $this->assertCount(0, $feed['messages']);

        $this->actingAs($this->crmStaff())
            ->post(route('crm.inquiry.sample.deliver', [InquiryHash::encode($inquiry->id), $sample->id]), [
                'delivery_courier' => 'Test Courier',
                'delivery_tracking_reference' => 'TRACK-123',
            ])->assertRedirect();
        $sample->refresh();
        $this->assertSame(FabricSampleRequest::STATUS_FORWARDED, $sample->status);

        $this->actingAs($client, 'client')
            ->post(route('client.sample.noted', $sample->id))->assertSessionHasNoErrors();
        $this->actingAs($client, 'client')
            ->post(route('client.sample.received', $sample->id))->assertSessionHasNoErrors();
        $this->actingAs($client, 'client')
            ->post(route('client.sample.approve', $sample->id))->assertSessionHasNoErrors();
        $this->assertSame(FabricSampleRequest::STATUS_APPROVED, $sample->fresh()->status);
    }

    public function test_chemist_can_reformulate_after_adjustment_request(): void    {
        Storage::fake('public');
        [$client, $product, $inquiry] = $this->seedConversation();

        $chemist = User::create([
            'name' => 'Lab Chemist',
            'email' => 'chemist2@test.local',
            'password' => Hash::make('password123'),
            'role' => 'MAN',
            'position' => 'staff',
            'manufacturing_role' => 'dyeing_lab_chemist',
            'is_active' => true,
        ]);
        $chemist = $this->withRole($chemist, 'MAN', 'staff', 'dyeing_lab_chemist');
        \App\Models\Core\PagePermission::create([
            'user_id' => $chemist->id, 'module' => 'MAN',
            'page' => 'production', 'permission_level' => 'edit',
        ]);

        $sample = FabricSampleRequest::create([
            'code' => 'FSR-TEST-00003',
            'inquiry_id' => $inquiry->id,
            'client_id' => $client->id,
            'product_id' => $product->id,
            'fabric_name' => $product->name,
            'color_description' => 'Deep royal blue',
            'status' => FabricSampleRequest::STATUS_ADJUSTMENT_REQUESTED,
            'adjustment_notes' => 'Make it deeper',
        ]);

        $this->actingAs($chemist)
            ->post(route('man.staff.dyeing-lab-chemist.sample.formulate', $sample->id), [
                'dyestuffs' => [['material_id' => $this->labMaterial('DYE-2', 'Blue RR', 'Dye'), 'name' => 'Blue RR', 'pct' => 3.0]],
                'yarns' => [['material_id' => $this->labMaterial('YRN-2', 'Cotton 30s', 'Yarn'), 'name' => 'Cotton 30s', 'qty' => 4]],
                'weave_design' => 'Twill 2/1',
                'sample_photo' => $this->fakeSwatchPhoto(),
            ])
            ->assertStatus(302);

        $sample->refresh();
        $this->assertSame(FabricSampleRequest::STATUS_AWAITING_CRM_REVIEW, $sample->status);
        $this->assertNull($sample->adjustment_notes);

        // The new formulation is held for CRM review before client delivery.
        $this->assertFalse((bool) $sample->messages()->firstOrFail()->visible_to_client);
    }

    public function test_meeting_cannot_be_scheduled_in_the_past(): void
    {
        [$client, $product, $inquiry] = $this->seedConversation();
        $staff = $this->crmStaff();
        $key = InquiryHash::encode($inquiry->id);

        $this->actingAs($staff)
            ->post(route('crm.inquiry.meeting', $key), [
                'scheduled_at' => now()->subDay()->format('Y-m-d H:i:s'),
                'location' => 'Zoom',
                'type' => 'video',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('scheduled_at');

        $this->actingAs($staff)
            ->post(route('crm.inquiry.meeting', $key), [
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'location' => 'Zoom',
                'type' => 'video',
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('conversation_messages', [
            'inquiry_id' => $inquiry->id,
            'is_system_event' => true,
        ]);
    }

    public function test_client_can_trash_accepted_quotation_and_request_new(): void
    {
        [$client, $product, $inquiry] = $this->seedConversation();

        $qid = DB::table('eco_quotations')->insertGetId([
            'inquiry_id' => $inquiry->id,
            'client_id' => $client->id,
            'quotation_number' => 'ECO-QT-TRASH-1',
            'status' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sentId = DB::table('eco_quotations')->insertGetId([
            'inquiry_id' => $inquiry->id,
            'client_id' => $client->id,
            'quotation_number' => 'ECO-QT-SENT-1',
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Only accepted quotations can be trashed (a sent one is refused
        // and keeps its status — the 403 surfaces as a redirect for
        // grantless portal accounts via the app's exception handler).
        $this->actingAs($client, 'client')
            ->post(route('client.quotation.trash', $sentId), ['notes' => 'changed mind'])
            ->assertForbidden();
        $this->assertSame(
            'sent',
            DB::table('eco_quotations')->find($sentId)->status
        );

        $this->actingAs($client, 'client')
            ->post(route('client.quotation.trash', $qid), ['notes' => 'changed mind'])
            ->assertStatus(302);

        $trashed = DB::table('eco_quotations')->find($qid);
        $this->assertSame('trashed', $trashed->status);
        $this->assertTrue((bool) $trashed->request_new_quote);
        $this->assertDatabaseHas('conversation_messages', [
            'inquiry_id' => $inquiry->id,
        ]);
        // A trashed quotation no longer counts as the pricing source.
        $this->assertSame(
            0,
            DB::table('eco_quotations')->where('inquiry_id', $inquiry->id)->where('status', 'accepted')->count()
        );
    }

    public function test_inquiry_show_exposes_lab_recipe_for_job_order(): void
    {
        [$client, $product, $inquiry] = $this->seedConversation();
        $staff = $this->crmStaff();

        $recipe = \App\Models\Man\BomRecord::create([
            'client_id' => $client->id,
            'product_id' => $product->id,
            'yarn_type' => '100% Cotton',
            'dye_color' => 'Deep royal blue',
            'weave_design' => 'Twill 2/1',
            'materials' => ['Blue RR' => 2.5],
        ]);
        FabricSampleRequest::create([
            'code' => 'FSR-TEST-JO',
            'inquiry_id' => $inquiry->id,
            'client_id' => $client->id,
            'product_id' => $product->id,
            'fabric_name' => $product->name,
            'color_description' => 'Deep royal blue',
            'status' => FabricSampleRequest::STATUS_APPROVED,
            'recipe_id' => $recipe->id,
        ]);

        $this->actingAs($staff)
            ->get(route('crm.inquiry.show', InquiryHash::encode($inquiry->id)))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->has('sampleRecipes', 1)
                ->where('sampleRecipes.0.sample_code', 'FSR-TEST-JO')
                ->where('sampleRecipes.0.recipe.yarn_type', '100% Cotton')
                ->where('sampleRecipes.0.recipe.product_id', $product->id));
    }

    public function test_inquiry_show_exposes_approved_quotation_tiers(): void
    {
        [$client, $product, $inquiry] = $this->seedConversation();
        $staff = $this->crmStaff();

        $qid = DB::table('eco_quotations')->insertGetId([
            'inquiry_id' => $inquiry->id,
            'client_id' => $client->id,
            'quotation_number' => 'ECO-QT-TEST-1',
            'status' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('eco_quotation_items')->insert([
            'eco_quotation_id' => $qid,
            'fabric' => $product->name,
            'color' => 'White',
            'kilos' => 10,
            'unit_price' => 150,
            'price' => 1500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // A second (older) accepted quotation on the same conversation.
        $qid2 = DB::table('eco_quotations')->insertGetId([
            'inquiry_id' => $inquiry->id,
            'client_id' => $client->id,
            'quotation_number' => 'ECO-QT-TEST-0',
            'status' => 'accepted',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        DB::table('eco_quotation_items')->insert([
            'eco_quotation_id' => $qid2,
            'fabric' => $product->name,
            'color' => 'White',
            'kilos' => 10,
            'unit_price' => 120,
            'price' => 1200,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->actingAs($staff)
            ->get(route('crm.inquiry.show', InquiryHash::encode($inquiry->id)))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->where('approvedQuotation.quotation_number', 'ECO-QT-TEST-1')
                ->where('approvedQuotation.items.0.color', 'White')
                ->where('approvedQuotation.items.0.unit_price', '150.00')
                ->has('acceptedQuotations', 2)
                ->where('acceptedQuotations.0.quotation_number', 'ECO-QT-TEST-1')
                ->where('acceptedQuotations.1.quotation_number', 'ECO-QT-TEST-0'));
    }
}
