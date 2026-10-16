<?php

namespace Tests\Feature;

use App\Models\Crm\Client;
use App\Models\Crm\FabricSampleRequest;
use App\Models\Eco\Inquiry;
use App\Models\Inv\Product;
use App\Models\Man\BomRecord;
use App\Models\Core\PagePermission;
use App\Models\Core\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChemistSampleHistoryTest extends TestCase
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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('sku')->nullable();
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
        Schema::create('lab_dip_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->timestamp('processed_at')->nullable();
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
            $table->string('urgency', 16)->default('normal');
            $table->string('status', 32)->default('requested');
            $table->json('formula')->nullable();
            $table->string('sample_image_path')->nullable();
            $table->unsignedBigInteger('recipe_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('formulated_by')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
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
            $table->timestamps();
        });
    }

    public function test_history_lists_formulated_colors_with_recipe_and_status(): void
    {
        $chemist = User::create([
            'name' => 'Lab Chemist',
            'email' => 'chemist@test.local',
            'password' => Hash::make('password123'),
            'role' => 'MAN',
            'position' => 'staff',
            'manufacturing_role' => 'dyeing_lab_chemist',
            'is_active' => true,
        ]);
        PagePermission::create([
            'user_id' => $chemist->id, 'module' => 'MAN',
            'page' => 'production', 'permission_level' => 'edit',
        ]);

        $client = Client::create(['company_name' => 'History Co', 'email' => 'h@test.local']);
        $product = Product::create(['name' => 'Cotton Twill', 'sku' => 'CT-001']);
        $inquiry = Inquiry::create(['client_id' => $client->id, 'product_id' => $product->id]);
        $recipe = BomRecord::create([
            'client_id' => $client->id,
            'product_id' => $product->id,
            'yarn_type' => '100% Cotton',
            'dye_color' => 'Deep royal blue',
            'weave_design' => 'Twill 2/1',
            'materials' => ['Blue RR' => 2.5],
        ]);
        FabricSampleRequest::create([
            'code' => 'FSR-HIST-00001',
            'inquiry_id' => $inquiry->id,
            'client_id' => $client->id,
            'product_id' => $product->id,
            'fabric_name' => $product->name,
            'color_description' => 'Deep royal blue',
            'status' => FabricSampleRequest::STATUS_APPROVED,
            'formula' => ['dyestuffs' => [['name' => 'Blue RR', 'pct' => 2.5]]],
            'sample_image_path' => 'fabric_samples/swatch.jpg',
            'recipe_id' => $recipe->id,
            'formulated_by' => $chemist->id,
        ]);

        $this->actingAs($chemist)
            ->get(route('man.staff.dyeing-lab-chemist.history'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->has('sampleHistory.data', 1)
                ->where('sampleHistory.data.0.code', 'FSR-HIST-00001')
                ->where('sampleHistory.data.0.status', FabricSampleRequest::STATUS_APPROVED)
                ->where('sampleHistory.data.0.fabric_name', 'Cotton Twill')
                ->where('sampleHistory.data.0.recipe.yarn_type', '100% Cotton')
                ->has('jobs'));
    }

    public function test_history_hides_other_chemists_samples(): void
    {
        $chemist = User::create([
            'name' => 'Lab Chemist',
            'email' => 'chemist@test.local',
            'password' => Hash::make('password123'),
            'role' => 'MAN',
            'position' => 'staff',
            'manufacturing_role' => 'dyeing_lab_chemist',
            'is_active' => true,
        ]);
        PagePermission::create([
            'user_id' => $chemist->id, 'module' => 'MAN',
            'page' => 'production', 'permission_level' => 'edit',
        ]);

        $client = Client::create(['company_name' => 'Other Co', 'email' => 'o@test.local']);
        $product = Product::create(['name' => 'Silk', 'sku' => 'SL-001']);
        $inquiry = Inquiry::create(['client_id' => $client->id, 'product_id' => $product->id]);
        FabricSampleRequest::create([
            'code' => 'FSR-HIST-00002',
            'inquiry_id' => $inquiry->id,
            'client_id' => $client->id,
            'product_id' => $product->id,
            'fabric_name' => $product->name,
            'color_description' => 'Crimson',
            'status' => FabricSampleRequest::STATUS_FORMULATED,
            'formulated_by' => $chemist->id + 999,
        ]);

        $this->actingAs($chemist)
            ->get(route('man.staff.dyeing-lab-chemist.history'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->has('sampleHistory.data', 0));
    }
}
