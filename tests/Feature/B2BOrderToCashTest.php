<?php

namespace Tests\Feature;

use App\Models\Core\User;
use App\Models\Crm\Client;
use App\Models\Eco\Inquiry;
use App\Models\Inv\Product;
use App\Models\Inv\Material;
use App\Services\Fin\FinanceService;
use App\Support\InquiryHash;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * End-to-end B2B order-to-cash walkthrough with zero skipped steps:
 *
 * inquire → negotiate (messages both sides) → meeting → quotation issued →
 * quotation accepted → PO intake (credit_review) → credit → client approval →
 * client accepts → job orders generated → production pipeline → packaging →
 * warehouse → load → dispatch → driver transit → POD → ORD sync → client
 * receiving → FIN invoice → payment → completed.
 */
class B2BOrderToCashTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
        Storage::fake('public');
    }

    public function test_full_order_to_cash_chain_without_skips(): void
    {
        // ── Actors ────────────────────────────────────────────────────
        $client = Client::create([
            'company_name' => 'Acme Garments', 'email' => 'buyer@acme.test',
            'password' => Hash::make('secret123'), 'status' => 'active',
        ]);
        $product = Product::create([
            'name' => 'Cotton Jersey', 'sku' => 'CJ-001', 'status' => 'Active',
            'product_id' => 'P-001', 'stock_on_hand' => 100,
        ]);
        $yarn = Material::create([
            'mat_id' => 'YRN-001', 'name' => 'Cotton 30s', 'category' => 'Yarn', 'unit' => 'kg',
        ]);
        $crm = $this->staff('CRM', 'manager', 'Ramon Cruz');
        $eco = $this->staff('ECO', 'manager', 'Eko Manager');
        $ord = $this->staff('ORD', 'manager', 'Ordy Manager');
        $log = $this->staff('LOG', 'manager', 'Logi Manager');
        $fin = $this->staff('FIN', 'manager', 'Fina Manager');
        $driverUser = $this->staff('LOG', 'staff', 'Dan Driver');

        // ── 1. Client inquires ────────────────────────────────────────
        $this->actingAs($client, 'client')
            ->post(route('client.products.inquire', $product), ['message' => 'Need 500kg cotton jersey, navy.'])
            ->assertRedirect();
        $inquiry = Inquiry::firstOrFail();
        $this->assertSame('open', $inquiry->status);
        $this->assertDatabaseCount('conversation_messages', 1);
        $hash = InquiryHash::encode($inquiry->id);

        // ── 2. Negotiation: messages both ways + realtime feed ────────
        $this->actingAs($client, 'client')
            ->post(route('client.conversation.message', $hash), ['message' => 'What is the lead time?'])
            ->assertSessionHasNoErrors();
        $this->actingAs($crm)
            ->post(route('crm.inquiry.message', $hash), ['message' => '7-10 working days.'])
            ->assertSessionHasNoErrors();
        $feed = $this->actingAs($client, 'client')->getJson(route('client.conversation.feed', ['inquiry' => $hash, 'after' => 0]));
        $feed->assertOk()->assertJsonCount(3, 'messages');

        // ── 3. Meeting scheduled ──────────────────────────────────────
        $this->actingAs($crm)
            ->post(route('crm.inquiry.meeting', $hash), [
                'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'location' => 'Showroom A', 'type' => 'onsite',
            ])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('conversation_messages', ['inquiry_id' => $inquiry->id, 'is_system_event' => true]);

        // ── 4. CRM requests a fabric sample (lab loop entry) ──────────
        // NOTE: {inquiry} binds hashed keys only — raw numeric IDs 404.
        // ── 5. Quotation issued → client accepts ──────────────────────
        $this->actingAs($crm)
            ->post(route('crm.inquiry.quotation', $hash), [
                'items' => [[
                    'product_id' => $product->id, 'fabric' => 'Cotton Jersey', 'fabric_type_id' => $yarn->id, 'design' => 'Plain',
                    'kilos' => 500, 'price_white' => 100, 'price_light' => 110, 'price_dark' => 120,
                ]],
                'vat_type' => 'exclusive', 'payment_terms' => '30 days',
            ])
            ->assertSessionHasNoErrors();
        $quotation = \App\Models\Eco\EcoQuotation::firstOrFail();
        $this->assertSame('sent', $quotation->status);
        $this->assertGreaterThan(0, (float) $quotation->grand_total);
        $this->actingAs($client, 'client')
            ->post(route('client.quotation.accept', $quotation), [])
            ->assertSessionHasNoErrors();
        $this->assertSame('accepted', $quotation->fresh()->status);

        $quoteItemId = $quotation->items()->where('color', 'Dark Colors')->value('id');
        $this->actingAs($client, 'client')->post(route('client.conversation.sample-request', $hash), [
            'request_method' => 'pantone',
            'fabric_designs' => [[
                'quotation_item_id' => $quoteItemId,
                'colors' => [[
                    'pantone_code' => '19-4052 TCX', 'pantone_color' => 'Classic Blue',
                    'texture' => 'Medium', 'gsm' => 180, 'reference_type' => 'hex', 'color_hex' => '#0F4C81',
                ]],
            ]],
        ])->assertRedirect();
        $sample = \App\Models\Crm\FabricSampleRequest::firstOrFail();
        $this->assertSame('client_submitted', $sample->status);
        $this->actingAs($crm)->post(route('crm.inquiry.sample.pass-to-lab', [$hash, $sample->id]))->assertRedirect();
        $chemist = $this->staff('MAN', 'staff', 'Dyeing Lab Chemist');
        \App\Models\Core\User::whereKey($chemist->id)->update(['manufacturing_role' => 'dyeing_lab_chemist']);
        $chemist->refresh();
        \App\Models\Core\PagePermission::create([
            'user_id' => $chemist->id, 'module' => 'MAN', 'page' => 'production', 'permission_level' => 'edit',
        ]);
        $dye = Material::create(['mat_id' => 'DYE-001', 'name' => 'Blue RR', 'category' => 'Dye', 'unit' => 'kg']);
        $this->actingAs($chemist)->patch(route('man.staff.dyeing-lab-chemist.sample.start', $sample->id))->assertRedirect();
        $this->actingAs($chemist)->post(route('man.staff.dyeing-lab-chemist.sample.formulate', $sample->id), [
            'dyestuffs' => [['material_id' => $dye->id, 'name' => 'Blue RR', 'pct' => 2.5]],
            'yarns' => [['material_id' => $yarn->id, 'name' => 'Cotton 30s', 'qty' => 5]],
            'weave_design' => 'Plain', 'sample_photo' => $this->fakePodPhoto(),
        ])->assertRedirect();
        $sample->refresh();
        $this->assertSame(\App\Models\Crm\FabricSampleRequest::STATUS_AWAITING_CRM_REVIEW, $sample->status);
        $this->actingAs($crm)->post(route('crm.inquiry.sample.deliver', [$hash, $sample->id]), [
            'delivery_courier' => 'Test Courier', 'delivery_tracking_reference' => 'SAMPLE-TRACK-1',
        ])->assertRedirect();
        $this->actingAs($client, 'client')->post(route('client.sample.noted', $sample->id))->assertRedirect();
        $this->actingAs($client, 'client')->post(route('client.sample.received', $sample->id))->assertRedirect();
        $this->actingAs($client, 'client')->post(route('client.sample.approve', $sample->id))->assertRedirect();
        $sample->refresh();
        $this->assertSame(\App\Models\Crm\FabricSampleRequest::STATUS_APPROVED, $sample->status);

        // ── 6. Client sends signed PO file ────────────────────────────
        $this->actingAs($client, 'client')
            ->post(route('client.conversation.send-po', $hash), [
                'po_files' => [UploadedFile::fake()->create('signed-po.pdf', 50, 'application/pdf')],
                'notes' => 'Signed PO attached.',
            ])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('eco_conversation_attachments', ['is_po' => true]);

        // ── 7. ORD intake: PO created at credit_review ─────────────────
        $this->actingAs($ord)
            ->post(route('ord.orders.store'), [
                'client_id' => $client->id,
                'items' => [['product_id' => $product->id, 'quantity' => 500, 'unit_price' => 120]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $po = \App\Models\Ord\PurchaseOrder::firstOrFail();
        $this->assertSame('credit_review', $po->status);

        // ── 8. ECO credit sends finalized quote to the client ─────────
        $this->actingAs($eco)
            ->post(route('eco.credit.approve-order', $po))
            ->assertSessionHasNoErrors();
        $this->assertSame('pending_client_approval', $po->fresh()->status);

        // ── 9. Client approves → approved ─────────────────────────────
        $this->actingAs($client, 'client')
            ->post(route('client.orders.accept', $po))
            ->assertSessionHasNoErrors();
        $this->assertSame('approved', $po->fresh()->status);

        // ── 10. Job orders generated → released_to_production ─────────
        $item = $po->items()->firstOrFail();
        $this->actingAs($ord)
            ->post(route('ord.orders.generate-so', $po), ['lines' => [[
                'purchase_order_item_id' => $item->id,
                'yarn_type' => 'Cotton', 'color' => 'Navy', 'design' => 'Plain',
                'quantity' => 500, 'unit_price' => 120, 'recipe_id' => $sample->fresh()->recipe_id,
            ]]] )
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $so = \App\Models\Ord\SalesOrder::firstOrFail();
        $this->assertSame('confirmed', $so->status);
        $this->assertSame('released_to_production', $po->fresh()->status);

        // ── 11. Production pipeline to ready_for_dispatch ─────────────
        $this->actingAs($ord)
            ->post(route('ord.productions.release', $so->id))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $mfg = \App\Models\Man\ManufacturingOrder::where('sales_order_id', $so->id)->firstOrFail();
        $this->assertSame('pending', $mfg->status);
        $this->assertSame($sample->fresh()->recipe_id, $so->fresh()->recipe_id);
        $mfg->update(['remaining_quantity' => 0, 'status' => 'completed']);
        foreach (['production_done', 'ready_for_dispatch'] as $step) {
            $this->actingAs($ord)
                ->post(route('ord.orders.transition', ['type' => 'so', 'id' => $so->id]), ['to' => $step])
                ->assertSessionHasNoErrors();
        }
        $this->assertSame('ready_for_dispatch', $so->fresh()->status);

        // ── 12. Packaging → warehouse → load → delivery (pending) ─────
        $finishingSupervisor = $this->staff('MAN', 'manager', 'Finishing Supervisor');
        \App\Models\Core\User::whereKey($finishingSupervisor->id)->update([
            'is_manufacturing_supervisor' => true, 'supervisor_department' => 'finishing',
        ]);
        $finishingSupervisor->refresh();
        $workOrder = \App\Models\Man\FinishingWorkOrder::create([
            'manufacturing_order_id' => $mfg->id, 'sales_order_id' => $so->id,
            'extraction_method' => 'hydro', 'extraction_assigned_to' => $finishingSupervisor->id,
            'drying_method' => 'tubetex', 'drying_assigned_to' => $finishingSupervisor->id,
            'status' => 'ready_logistics', 'quality_checked_by' => $finishingSupervisor->id,
            'quality_checked_at' => now(),
        ]);
        $group = \App\Models\Man\FinishingPackageGroup::create([
            'finishing_work_order_id' => $workOrder->id, 'code' => 'FG-TEST-001',
            'group_name' => 'Acme Garments', 'packed_by' => $finishingSupervisor->id, 'status' => 'packed',
        ]);
        foreach (range(1, 20) as $roll) {
            $fabric = \App\Models\Man\Fabric::create([
                'code' => 'FAB-TEST-' . str_pad((string) $roll, 3, '0', STR_PAD_LEFT),
                'manufacturing_order_id' => $mfg->id, 'sales_order_id' => $so->id,
                'yarn_type' => 'Cotton 30s', 'roll_no' => (string) $roll, 'weight' => 25,
                'operator_id' => $ord->id, 'shift' => 'day', 'processed_at' => now(), 'status' => 'squeezer',
            ]);
            $workItem = \App\Models\Man\FinishingWorkItem::create([
                'finishing_work_order_id' => $workOrder->id, 'fabric_id' => $fabric->id, 'status' => 'packed',
            ]);
            \App\Models\Man\FinishingPackageGroupItem::create([
                'finishing_package_group_id' => $group->id, 'finishing_work_item_id' => $workItem->id,
            ]);
        }
        $this->actingAs($finishingSupervisor)
            ->post(route('man.finishing-workflow.logistics', $workOrder))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $packageIds = \App\Models\War\WarehousePackage::where('manufacturing_order_id', $mfg->id)->pluck('id')->all();
        $this->assertCount(20, $packageIds);
        $this->assertSame(500.0, (float) \App\Models\War\WarehousePackage::whereIn('id', $packageIds)->sum('weight_kg'));
        $secondClient = Client::create([
            'company_name' => 'Northstar Apparel', 'email' => 'buyer@northstar.test',
            'password' => Hash::make('secret123'), 'status' => 'active',
        ]);
        $secondSo = \App\Models\Ord\SalesOrder::create([
            'client_id' => $secondClient->id, 'jo_number' => 'JO-NORTHSTAR-001',
            'control_number' => 'CTL-NORTHSTAR-001', 'color' => 'Red', 'quantity' => 100,
            'unit_price' => 120, 'subtotal' => 12000, 'tax_rate' => 12, 'tax_amount' => 1440,
            'total_amount' => 13440, 'yarn_type' => 'Cotton', 'design' => 'Jersey',
            'recipe_id' => $sample->fresh()->recipe_id, 'status' => 'ready_for_dispatch',
        ]);
        $secondMfg = \App\Models\Man\ManufacturingOrder::create([
            'sales_order_id' => $secondSo->id, 'total_quantity' => 100,
            'remaining_quantity' => 0, 'status' => 'completed',
        ]);
        $secondPackage = \App\Models\War\WarehousePackage::create([
            'package_number' => 'WP-NORTHSTAR-001', 'manufacturing_order_id' => $secondMfg->id,
            'product_id' => $product->id, 'quantity' => 100, 'weight_kg' => 100, 'status' => 'pushed_to_logistics',
        ]);
        $packageIds[] = $secondPackage->id;
        $route = \App\Models\Logistics\Route::create([
            'name' => 'Plant → Acme', 'client_id' => $client->id,
            'origin' => 'Plant', 'destination' => 'Acme site',
        ]);
        $truck = \App\Models\Logistics\Truck::create(['truck_number' => 'TRK-1', 'status' => 'available', 'max_load_kg' => 300]);
        $firstTruckPackages = array_slice($packageIds, 0, 12);
        $secondTruckPackages = array_slice($packageIds, 12);
        $this->actingAs($log)
            ->post(route('logistics.load.pass'), ['package_ids' => $packageIds])
            ->assertSessionHasNoErrors();

        // ── 13. Dispatch → transit → POD → delivered ──────────────────
        $driver = \App\Models\Logistics\Driver::create(['user_id' => $driverUser->id, 'is_available' => true]);
        $this->actingAs($log)
            ->post(route('logistics.dispatch.store'), [
                'truck_id' => $truck->id, 'driver_id' => $driver->id,
                'package_ids' => $firstTruckPackages, 'scheduled_departure' => now()->addHour()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('logistics.dispatch.index'))
            ->assertSessionHasNoErrors();
        $delivery = \App\Models\Logistics\Delivery::firstOrFail();
        $this->assertSame('dispatched', $delivery->fresh()->status);
        $this->actingAs($driverUser)
            ->post(route('logistics.driver.transit', $delivery))
            ->assertSessionHasNoErrors();
        $this->actingAs($driverUser)
            ->post(route('logistics.driver.proof', $delivery), [
                'image' => $this->fakePodPhoto(), 'notes' => 'Left at dock.',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertDatabaseHas('proof_of_deliveries', ['delivery_id' => $delivery->id]);

        // ── 14. ORD syncs POD → SO delivered ──────────────────────────
        $this->actingAs($ord)
            ->post(route('ord.delivery.sync', $delivery))
            ->assertSessionHasNoErrors();
        $this->assertSame('in_transit', $so->fresh()->status, 'A partial JO delivery must not mark the whole JO delivered.');

        $truck2 = \App\Models\Logistics\Truck::create(['truck_number' => 'TRK-2', 'status' => 'available', 'max_load_kg' => 300]);
        $driverUser2 = $this->staff('LOG', 'staff', 'Dan Driver Two');
        $driver2 = \App\Models\Logistics\Driver::create(['user_id' => $driverUser2->id, 'is_available' => true]);
        $this->actingAs($log)->post(route('logistics.dispatch.store'), [
            'truck_id' => $truck2->id, 'driver_id' => $driver2->id,
            'package_ids' => $secondTruckPackages, 'scheduled_departure' => now()->addHours(2)->format('Y-m-d H:i:s'),
        ])->assertRedirect(route('logistics.dispatch.index'))->assertSessionHasNoErrors();
        $delivery2 = \App\Models\Logistics\Delivery::latest('id')->firstOrFail();
        $this->assertCount(2, $delivery2->stops, 'One truck load can make separate client stops for multiple JOs.');
        $this->actingAs($driverUser2)->post(route('logistics.driver.transit', $delivery2))->assertSessionHasNoErrors();
        $this->actingAs($driverUser2)->post(route('logistics.driver.proof', $delivery2), [
            'image' => $this->fakePodPhoto(), 'notes' => 'Remaining rolls delivered.',
        ])->assertSessionHasNoErrors();
        $this->actingAs($ord)->post(route('ord.delivery.sync', $delivery2))->assertSessionHasNoErrors();
        $this->assertSame('delivered', $so->fresh()->status);
        $this->assertSame('delivered', $secondSo->fresh()->status);

        // ── 15. Client confirms receipt ───────────────────────────────
        $this->actingAs($client, 'client')
            ->post(route('client.receiving.mark', $delivery))
            ->assertSessionHasNoErrors();
        $this->actingAs($client, 'client')
            ->post(route('client.receiving.mark', $delivery2))
            ->assertSessionHasNoErrors();
        $this->actingAs($secondClient, 'client')
            ->post(route('client.receiving.mark', $delivery2))
            ->assertSessionHasNoErrors();

        // ── 16. FIN invoice syncs from the job order → paid in full ──
        app(FinanceService::class)->syncInvoices();
        $invoice = \App\Models\Fin\FinInvoice::where('sales_order_id', $so->id)->firstOrFail();
        $this->actingAs($fin)
            ->post(route('fin.manager.receivables.pay', $invoice), [
                'amount' => (float) $invoice->amount, 'method' => 'Cash',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('paid', $so->fresh()->payment_status);
        $this->assertSame('completed', $so->fresh()->status);

        // ── 17. Client portal reflects the whole journey ──────────────
        $this->actingAs($client, 'client')->get(route('client.dashboard'))->assertOk();
        $this->actingAs($client, 'client')->get(route('client.tracking'))->assertOk();
        $this->actingAs($client, 'client')
            ->get(route('client.tracking.show', ['type' => 'so', 'id' => $so->id]))
            ->assertOk();
    }

    public function test_credit_reject_and_tier_path_stay_connected(): void
    {
        $client = Client::create([
            'company_name' => 'Reject Co', 'email' => 'r@reject.test',
            'password' => Hash::make('secret123'), 'status' => 'active',
        ]);
        $product = Product::create(['name' => 'Denim', 'sku' => 'DN-1', 'status' => 'Active', 'product_id' => 'P-9']);
        $eco = $this->staff('ECO', 'manager', 'Eko Two');
        $ord = $this->staff('ORD', 'manager', 'Ordy Two');

        // Reject path → cancelled with audit trail.
        $this->actingAs($ord)->post(route('ord.orders.store'), [
            'client_id' => $client->id,
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 50]],
        ])->assertSessionHasNoErrors();
        $po = \App\Models\Ord\PurchaseOrder::firstOrFail();
        $this->actingAs($eco)
            ->post(route('eco.credit.reject', $po), ['reason' => 'Over credit limit.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $po->fresh()->status);

        // Tier path: credit_review → tier_assignment → pending_client_approval.
        $this->actingAs($ord)->post(route('ord.orders.store'), [
            'client_id' => $client->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 20]],
        ])->assertSessionHasNoErrors();
        $po2 = \App\Models\Ord\PurchaseOrder::latest('id')->firstOrFail();
        $this->actingAs($ord)
            ->post(route('ord.orders.transition', ['type' => 'po', 'id' => $po2->id]), ['to' => 'tier_assignment'])
            ->assertSessionHasNoErrors();
        $this->actingAs($eco)
            ->post(route('eco.credit.approve-order', $po2))
            ->assertSessionHasNoErrors();
        $this->assertSame('pending_client_approval', $po2->fresh()->status);
        $this->assertGreaterThanOrEqual(2, $po2->statusHistory()->count());
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * A real 1x1 JPEG (no GD needed) so the `image` rule passes.
     */
    private function fakePodPhoto(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pod') . '.jpg';
        file_put_contents($path, base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAQEBAQAAAAAAAAAAARP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8AVQA//9k='
        ));

        return new UploadedFile($path, 'pod.jpg', 'image/jpeg', null, true);
    }

    private function staff(string $role, string $position, string $name): User
    {
        // NOTE: role/position/is_active are NOT mass-assignable on User, so
        // create() silently drops them (leaving NULL in memory, which fails
        // every module gate). Write them via query and return a fresh model.
        $user = User::create([
            'name' => $name, 'email' => str()->slug($name) . '@monti.test',
            'password' => Hash::make('secret123'),
        ]);
        User::where('id', $user->id)->update([
            'role' => $role, 'position' => $position, 'is_active' => true,
        ]);

        return $user->fresh();
    }

    private function buildSchema(): void
    {
        // Fresh in-memory DB per test (the app migrations are MySQL-only,
        // so each table is rebuilt here like the other feature tests do).
        foreach ([
            'users', 'page_permissions', 'user_module_access', 'workforce_permissions',
            'crm_page_permissions', 'crm_client_assignments', 'hrm_departments',
            'clients', 'client_quotations', 'credit_accounts', 'products', 'inquiries',
            'conversation_messages', 'eco_conversation_attachments', 'eco_quotations',
            'eco_quotation_items', 'fabric_sample_requests', 'fabric_sample_formulations', 'purchase_orders',
            'purchase_order_items', 'sales_orders', 'order_status_histories', 'order_queues',
            'manufacturing_orders', 'warehouse_packages', 'delivery_packages',
            'deliveries', 'routes', 'trucks', 'drivers', 'conductors',
            'proof_of_deliveries', 'fin_invoices', 'fin_invoice_payments',
            'order_returns', 'bom_records', 'materials', 'delivery_stops', 'ceo_locations', 'client_notifications', 'notifications',
            'fabrics', 'finishing_work_orders', 'finishing_work_items', 'finishing_package_groups', 'finishing_package_group_items',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->unique();
            $t->string('password'); $t->string('role')->default('HRM');
            $t->string('position')->default('staff');
            $t->string('employee_id')->nullable();
            $t->string('manufacturing_role')->nullable();
            $t->boolean('is_manufacturing_supervisor')->default(false);
            $t->string('supervisor_department')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('page_permissions', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id'); $t->string('module');
            $t->string('page'); $t->string('permission_level')->nullable(); $t->timestamps();
        });
        Schema::create('user_module_access', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id'); $t->string('module'); $t->timestamps();
        });
        Schema::create('workforce_permissions', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id'); $t->timestamps();
        });
        Schema::create('crm_page_permissions', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id'); $t->string('page')->nullable(); $t->timestamps();
        });
        Schema::create('crm_client_assignments', function (Blueprint $t) {
            $t->id(); $t->foreignId('staff_id'); $t->foreignId('client_id'); $t->timestamps();
        });
        Schema::create('hrm_departments', function (Blueprint $t) {
            $t->id(); $t->string('name')->nullable(); $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('company_name')->nullable();
            $t->string('email')->nullable(); $t->string('password')->nullable();
            $t->string('contact_person')->nullable(); $t->string('status')->nullable();
            $t->decimal('credit_limit', 12, 2)->default(0);
            $t->rememberToken(); $t->timestamps(); $t->softDeletes();
        });
        // Read by HandleInertiaRequests to resolve the account's primary login.
        Schema::create('crm_contacts', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('client_id')->nullable();
            $t->string('name')->nullable(); $t->string('email')->nullable();
            $t->boolean('is_primary')->default(false);
            $t->string('status')->default('active');
            $t->boolean('has_account')->default(false);
            $t->json('access_pages')->nullable();
            $t->timestamps();
        });
        Schema::create('client_quotations', function (Blueprint $t) {
            $t->id(); $t->foreignId('client_id');
            $t->string('quotation_number')->nullable(); $t->string('status')->default('sent');
            $t->decimal('subtotal', 12, 2)->default(0); $t->decimal('grand_total', 12, 2)->default(0);
            $t->text('custom_notes')->nullable(); $t->timestamps();
        });
        Schema::create('credit_accounts', function (Blueprint $t) {
            $t->id(); $t->foreignId('client_id');
            $t->decimal('outstanding_balance', 12, 2)->default(0);
            $t->boolean('is_good_payer')->default(true); $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id(); $t->string('name')->nullable(); $t->string('sku')->nullable();
            $t->string('product_id')->nullable(); $t->string('category')->nullable();
            $t->string('status')->default('Active'); $t->text('colors')->nullable();
            $t->integer('stock_on_hand')->default(0); $t->timestamps();
        });
        Schema::create('materials', function (Blueprint $t) {
            $t->id(); $t->string('mat_id')->nullable(); $t->string('name');
            $t->string('category'); $t->string('unit')->nullable(); $t->string('yarn_tier')->nullable();
            $t->integer('reorder_point')->nullable(); $t->decimal('unit_cost', 12, 2)->nullable(); $t->timestamps();
        });
        Schema::create('inquiries', function (Blueprint $t) {
            $t->id(); $t->foreignId('client_id'); $t->foreignId('product_id')->nullable();
            $t->text('initial_message')->nullable(); $t->string('status')->default('open');
            $t->timestamp('last_message_at')->nullable(); $t->timestamps();
        });
        Schema::create('conversation_messages', function (Blueprint $t) {
            $t->id(); $t->foreignId('inquiry_id'); $t->string('sender_type')->nullable();
            $t->text('message')->nullable(); $t->string('attachment')->nullable();
            $t->string('reject_reason')->nullable(); $t->boolean('request_new_quote')->default(false);
            $t->text('meeting_data')->nullable(); $t->boolean('is_system_event')->default(false);
            $t->boolean('visible_to_client')->default(true);
            $t->foreignId('sample_request_id')->nullable(); $t->timestamps();
        });
        Schema::create('eco_conversation_attachments', function (Blueprint $t) {
            $t->id(); $t->foreignId('conversation_message_id');
            $t->string('file_path')->nullable(); $t->string('file_name')->nullable();
            $t->string('file_type')->nullable(); $t->boolean('approved_by_client')->default(false);
            $t->boolean('is_po')->default(false); $t->timestamps();
        });
        Schema::create('eco_quotations', function (Blueprint $t) {
            $t->id(); $t->foreignId('client_id'); $t->foreignId('inquiry_id')->nullable();
            $t->string('quotation_number'); $t->string('vat_type')->nullable();
            $t->string('payment_terms')->nullable(); $t->text('notes')->nullable();
            $t->decimal('grand_total', 12, 2)->default(0); $t->string('status')->default('sent');
            $t->string('reject_reason')->nullable(); $t->boolean('request_new_quote')->default(false);
            $t->timestamps();
        });
        Schema::create('eco_quotation_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('eco_quotation_id'); $t->foreignId('product_id')->nullable();
            $t->foreignId('fabric_type_id')->nullable(); $t->string('fabric_type')->nullable();
            $t->string('fabric')->nullable(); $t->string('design')->nullable();
            $t->string('color')->nullable(); $t->decimal('kilos', 12, 2)->default(0);
            $t->decimal('unit_price', 12, 2)->default(0); $t->decimal('price', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('fabric_sample_requests', function (Blueprint $t) {
            $t->id(); $t->string('code')->nullable();
            $t->foreignId('inquiry_id'); $t->foreignId('client_id');
            $t->foreignId('product_id')->nullable(); $t->string('fabric_name')->nullable();
            $t->text('color_description')->nullable(); $t->text('notes')->nullable();
            $t->text('adjustment_notes')->nullable(); $t->string('urgency')->default('normal');
            $t->string('status')->default('requested'); $t->text('formula')->nullable();
            $t->string('sample_image_path')->nullable(); $t->foreignId('recipe_id')->nullable();
            $t->foreignId('requested_by')->nullable(); $t->foreignId('formulated_by')->nullable();
            $t->foreignId('parent_id')->nullable(); $t->string('request_method')->nullable();
            $t->string('texture')->nullable(); $t->integer('gsm')->nullable(); $t->string('color_hex')->nullable();
            $t->string('color_image_path')->nullable(); $t->string('carrier_name')->nullable();
            $t->string('client_reference_path')->nullable();
            $t->json('pantone_colors')->nullable(); $t->json('pantone_formula')->nullable();
            $t->timestamp('delivery_sent_at')->nullable();
            $t->timestamp('client_noted_at')->nullable(); $t->timestamp('client_received_at')->nullable();
            $t->string('delivery_courier')->nullable(); $t->string('delivery_tracking_reference')->nullable();
            $t->text('delivery_notes')->nullable(); $t->string('delivery_group_code')->nullable();
            $t->unsignedBigInteger('delivery_message_id')->nullable(); $t->timestamps();
        });
        Schema::create('fabric_sample_formulations', function (Blueprint $t) {
            $t->id(); $t->foreignId('fabric_sample_request_id'); $t->unsignedSmallInteger('version');
            $t->json('formula'); $t->string('attachment_path'); $t->string('attachment_name');
            $t->string('attachment_mime'); $t->foreignId('submitted_by')->nullable();
            $t->string('status')->default('awaiting_crm'); $t->foreignId('reviewed_by')->nullable();
            $t->timestamp('reviewed_at')->nullable(); $t->text('review_notes')->nullable();
            $t->foreignId('conversation_message_id')->nullable(); $t->timestamps();
        });
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id(); $t->foreignId('client_id'); $t->string('po_number');
            $t->decimal('subtotal', 12, 2)->default(0); $t->decimal('discount_amount', 12, 2)->default(0);
            $t->decimal('tax_rate', 5, 2)->default(12); $t->decimal('tax_amount', 12, 2)->default(0);
            $t->decimal('total_amount', 12, 2)->default(0); $t->string('status')->default('credit_review');
            $t->string('tier_level')->nullable(); $t->text('notes')->nullable();
            $t->date('delivery_date')->nullable(); $t->string('attachment_path')->nullable();
            $t->string('control_number')->nullable(); $t->string('yarn')->nullable();
            $t->date('expected_ship_date')->nullable(); $t->string('priority')->default('normal');
            $t->timestamp('confirmed_at')->nullable(); $t->foreignId('confirmed_by')->nullable();
            $t->string('cancel_reason')->nullable(); $t->string('on_hold_reason')->nullable();
            $t->string('payment_status')->nullable(); $t->string('receipt_file')->nullable();
            $t->timestamps();
        });
        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('purchase_order_id'); $t->foreignId('product_id');
            $t->integer('quantity')->default(0); $t->decimal('unit_price', 12, 2)->default(0);
            $t->decimal('line_total', 12, 2)->nullable(); $t->timestamps();
        });
        Schema::create('sales_orders', function (Blueprint $t) {
            $t->id(); $t->string('purchase_order_id')->nullable(); $t->foreignId('client_id');
            $t->string('jo_number'); $t->string('control_number')->nullable();
            $t->string('color')->nullable(); $t->decimal('quantity', 12, 2)->default(0);
            $t->decimal('unit_price', 12, 2)->default(0); $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('tax_rate', 5, 2)->default(12); $t->decimal('tax_amount', 12, 2)->default(0);
            $t->decimal('total_amount', 12, 2)->default(0);
            $t->string('yarn_type')->nullable(); $t->string('design')->nullable();
            $t->foreignId('recipe_id')->nullable(); $t->string('status')->default('pending');
            $t->string('pushed_to')->nullable(); $t->timestamp('knitting_done_at')->nullable();
            $t->foreignId('knitting_done_by')->nullable(); $t->date('expected_ship_date')->nullable();
            $t->string('priority')->default('normal'); $t->timestamp('confirmed_at')->nullable();
            $t->foreignId('confirmed_by')->nullable(); $t->timestamp('production_started_at')->nullable();
            $t->timestamp('production_done_at')->nullable(); $t->timestamp('delivered_at')->nullable();
            $t->string('cancel_reason')->nullable(); $t->string('on_hold_reason')->nullable();
            $t->foreignId('created_by')->nullable(); $t->string('payment_status')->nullable();
            $t->string('receipt_file')->nullable(); $t->timestamps();
        });
        Schema::create('order_status_histories', function (Blueprint $t) {
            $t->id(); $t->string('orderable_type')->nullable(); $t->foreignId('orderable_id')->nullable();
            $t->string('order_type')->nullable(); $t->foreignId('order_id')->nullable();
            $t->string('from_status')->nullable(); $t->string('to_status');
            $t->foreignId('changed_by')->nullable(); $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('order_queues', function (Blueprint $t) {
            $t->id(); $t->foreignId('purchase_order_id')->nullable(); $t->foreignId('sales_order_id')->nullable();
            $t->string('stage')->nullable(); $t->timestamp('scm_received_at')->nullable();
            $t->timestamp('inv_checked_at')->nullable(); $t->timestamp('man_started_at')->nullable();
            $t->timestamp('completed_at')->nullable(); $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('manufacturing_orders', function (Blueprint $t) {
            $t->id(); $t->foreignId('purchase_order_id')->nullable();
            $t->foreignId('sales_order_id')->nullable(); $t->integer('total_quantity')->default(0);
            $t->integer('remaining_quantity')->default(0); $t->string('status')->default('pending');
            $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('fabrics', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->foreignId('manufacturing_order_id')->nullable();
            $t->foreignId('sales_order_id')->nullable(); $t->foreignId('machine_id')->nullable();
            $t->foreignId('dyeing_remake_request_id')->nullable(); $t->string('yarn_type');
            $t->string('roll_no')->nullable(); $t->decimal('weight', 10, 2); $t->text('remarks')->nullable();
            $t->foreignId('operator_id')->nullable(); $t->string('shift')->nullable();
            $t->timestamp('processed_at')->nullable(); $t->string('status')->default('pending'); $t->timestamps();
        });
        Schema::create('finishing_work_orders', function (Blueprint $t) {
            $t->id(); $t->foreignId('manufacturing_order_id')->unique(); $t->foreignId('sales_order_id');
            $t->string('extraction_method'); $t->foreignId('extraction_assigned_to');
            $t->string('drying_method')->nullable(); $t->foreignId('drying_assigned_to')->nullable();
            $t->string('status')->default('extraction'); $t->foreignId('quality_checked_by')->nullable();
            $t->timestamp('quality_checked_at')->nullable(); $t->text('quality_remarks')->nullable(); $t->timestamps();
        });
        Schema::create('finishing_work_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('finishing_work_order_id'); $t->foreignId('fabric_id')->unique();
            $t->string('status')->default('extraction'); $t->timestamps();
        });
        Schema::create('finishing_package_groups', function (Blueprint $t) {
            $t->id(); $t->foreignId('finishing_work_order_id'); $t->string('code')->unique();
            $t->string('group_name')->nullable(); $t->foreignId('packed_by'); $t->string('status')->default('packed');
            $t->foreignId('warehouse_package_id')->nullable(); $t->timestamp('sent_to_logistics_at')->nullable(); $t->timestamps();
        });
        Schema::create('finishing_package_group_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('finishing_package_group_id'); $t->foreignId('finishing_work_item_id')->unique();
            $t->foreignId('warehouse_package_id')->nullable(); $t->timestamps();
        });
        Schema::create('warehouse_packages', function (Blueprint $t) {
            $t->id(); $t->string('package_number');
            $t->foreignId('manufacturing_order_id')->nullable(); $t->foreignId('product_id')->nullable();
            $t->decimal('quantity', 12, 2)->default(0); $t->decimal('weight_kg', 12, 2)->nullable(); $t->string('status')->default('pending');
            $t->timestamp('pushed_at')->nullable(); $t->foreignId('pushed_by')->nullable(); $t->timestamps();
        });
        Schema::create('delivery_packages', function (Blueprint $t) {
            $t->foreignId('delivery_id'); $t->foreignId('warehouse_package_id');
            $t->foreignId('delivery_stop_id')->nullable(); $t->timestamps();
        });
        Schema::create('delivery_stops', function (Blueprint $t) {
            $t->id(); $t->foreignId('delivery_id'); $t->foreignId('client_id')->nullable();
            $t->integer('sequence')->default(1); $t->string('label')->nullable(); $t->text('address')->nullable();
            $t->decimal('latitude', 10, 7)->nullable(); $t->decimal('longitude', 10, 7)->nullable();
            $t->string('status')->default('pending'); $t->timestamp('arrived_at')->nullable();
            $t->text('notes')->nullable(); $t->string('outcome')->nullable(); $t->string('signature_path')->nullable();
            $t->string('pod_photo_path')->nullable(); $t->text('pod_notes')->nullable();
            $t->string('recipient_name')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamps();
        });
        Schema::create('deliveries', function (Blueprint $t) {
            $t->id(); $t->string('delivery_number');
            $t->foreignId('truck_id')->nullable(); $t->foreignId('driver_id')->nullable();
            $t->foreignId('conductor1_id')->nullable(); $t->foreignId('conductor2_id')->nullable();
            $t->foreignId('route_id')->nullable(); $t->decimal('origin_lat', 11, 8)->nullable();
            $t->decimal('origin_lng', 11, 8)->nullable(); $t->string('origin_label')->nullable();
            $t->decimal('total_distance_km', 10, 2)->nullable(); $t->integer('estimated_minutes')->nullable();
            $t->json('route_geometry')->nullable(); $t->string('status')->default('pending');
            $t->timestamp('scheduled_departure')->nullable(); $t->timestamp('actual_departure')->nullable();
            $t->timestamp('arrival_time')->nullable(); $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('routes', function (Blueprint $t) {
            $t->id(); $t->string('name')->nullable(); $t->foreignId('client_id')->nullable();
            $t->string('origin')->nullable(); $t->string('destination')->nullable(); $t->timestamps();
        });
        Schema::create('trucks', function (Blueprint $t) {
            $t->id(); $t->string('truck_number')->nullable(); $t->decimal('max_load_kg', 12, 2)->nullable();
            $t->string('status')->default('available'); $t->timestamps();
        });
        Schema::create('ceo_locations', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable(); $t->decimal('latitude', 11, 8)->nullable();
            $t->decimal('longitude', 11, 8)->nullable(); $t->integer('range_radius')->nullable();
            $t->boolean('is_active')->default(true); $t->string('label')->nullable();
            $t->string('place_name')->nullable(); $t->timestamps();
        });
        Schema::create('client_notifications', function (Blueprint $t) {
            $t->id(); $t->foreignId('client_id'); $t->string('type')->nullable();
            $t->string('title'); $t->text('body')->nullable(); $t->string('link_route')->nullable();
            $t->foreignId('delivery_id')->nullable(); $t->boolean('is_read')->default(false); $t->timestamps();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id'); $t->string('type'); $t->string('title');
            $t->text('body')->nullable(); $t->string('link_route')->nullable();
            $t->foreignId('memo_id')->nullable(); $t->boolean('is_read')->default(false);
            $t->foreignId('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('drivers', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id'); $t->string('license_number')->nullable();
            $t->boolean('is_available')->default(true); $t->timestamps();
        });
        Schema::create('conductors', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable();
            $t->boolean('is_available')->default(true); $t->timestamps();
        });
        Schema::create('proof_of_deliveries', function (Blueprint $t) {
            $t->id(); $t->foreignId('delivery_id'); $t->string('image_path')->nullable();
            $t->text('notes')->nullable(); $t->timestamp('delivered_at')->nullable(); $t->timestamps();
        });
        Schema::create('fin_invoices', function (Blueprint $t) {
            $t->id(); $t->string('invoice_no'); $t->foreignId('sales_order_id')->nullable();
            $t->foreignId('client_id')->nullable(); $t->string('client_name')->nullable();
            $t->decimal('amount', 12, 2)->default(0); $t->date('due_date')->nullable();
            $t->string('status')->default('unpaid'); $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('fin_invoice_payments', function (Blueprint $t) {
            $t->id(); $t->foreignId('fin_invoice_id'); $t->decimal('amount', 12, 2)->default(0);
            $t->date('paid_at')->nullable(); $t->string('method')->nullable();
            $t->string('reference')->nullable(); $t->foreignId('recorded_by')->nullable(); $t->timestamps();
        });
        Schema::create('order_returns', function (Blueprint $t) {
            $t->id(); $t->foreignId('sales_order_id')->nullable();
            $t->string('return_number')->nullable(); $t->string('type')->nullable();
            $t->decimal('quantity', 12, 2)->default(0); $t->string('status')->nullable();
            $t->text('reason')->nullable(); $t->timestamps();
        });
        Schema::create('bom_records', function (Blueprint $t) {
            $t->id(); $t->foreignId('client_id')->nullable(); $t->foreignId('product_id')->nullable();
            $t->foreignId('fabric_sample_request_id')->nullable();
            $t->string('yarn_type')->nullable(); $t->string('dye_color')->nullable();
            $t->string('weave_design')->nullable(); $t->text('materials')->nullable(); $t->timestamps();
        });
    }
}
