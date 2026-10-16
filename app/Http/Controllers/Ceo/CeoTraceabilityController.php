<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Models\Logistics\Delivery;
use App\Models\Man\DyeJobChemical;
use App\Models\Man\Fabric;
use App\Models\Man\ManufacturingInventoryItem;
use App\Models\Man\Package as ManPackage;
use App\Models\Ord\PurchaseOrder;
use App\Models\Ord\SalesOrder;
use App\Models\Scm\PurchaseInvoice;
use App\Models\War\WarehousePackage;
use App\Models\War\WarehouseReceiving;
use App\Models\War\WarehouseStockItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CeoTraceabilityController extends Controller
{
    /**
     * Genealogy search + quick-pick lists (recent fabrics, packages,
     * deliveries). ?mode=lot|unit&q=... resolves one trace.
     */
    public function index(Request $request)
    {
        $mode = $request->get('mode', 'unit');
        $q = trim((string) $request->get('q', ''));

        return Inertia::render('Dashboard/CEO/Traceability', [
            'filters' => ['mode' => $mode, 'q' => $q],
            'trace' => $q !== '' ? $this->resolve($mode, $q) : null,
            'recentFabrics' => Fabric::with('salesOrder.client')->latest()->take(12)->get()
                ->map(fn ($f) => ['code' => $f->code, 'yarn' => $f->yarn_type, 'status' => $f->status, 'client' => $f->salesOrder?->client?->company_name]),
            'recentPackages' => WarehousePackage::with('manufacturingOrder.salesOrder.client')->latest()->take(12)->get()
                ->map(fn ($p) => ['number' => $p->package_number, 'qty' => $p->quantity, 'status' => $p->status, 'jo' => $p->manufacturingOrder?->salesOrder?->jo_number]),
            'recentLots' => WarehouseStockItem::with('material')->latest()->take(12)->get()
                ->map(fn ($s) => ['control' => $s->control_number, 'material' => $s->material?->name, 'qty' => $s->quantity, 'unit' => $s->unit, 'status' => $s->status]),
        ]);
    }

    protected function resolve(string $mode, string $q): array
    {
        if ($mode === 'lot') {
            return $this->traceLot($q);
        }

        // Finished unit: fabric code → MAN package → warehouse package → JO → PO.
        if ($f = Fabric::where('code', $q)->first()) {
            return $this->traceFabric($f);
        }
        if ($p = ManPackage::where('code', $q)->first()) {
            return $this->traceFabric($p->fabric ?? Fabric::find($p->fabric_id));
        }
        if ($wp = WarehousePackage::where('package_number', $q)->first()) {
            $mo = $wp->manufacturingOrder;
            $fabric = $mo ? Fabric::where('manufacturing_order_id', $mo->id)->latest()->first() : null;
            if ($fabric) {
                return $this->traceFabric($fabric);
            }
            return $this->notFound($q, 'Warehouse package has no fabrics yet.');
        }
        if ($so = SalesOrder::where('jo_number', $q)->first()) {
            $fabric = Fabric::where('sales_order_id', $so->id)->latest()->first();
            if ($fabric) {
                return $this->traceFabric($fabric);
            }
            return $this->orderOnlyTrace($so);
        }
        if ($po = PurchaseOrder::where('po_number', $q)->first()) {
            $so = SalesOrder::where('purchase_order_id', $q)->latest()->first();
            if ($so && ($fabric = Fabric::where('sales_order_id', $so->id)->latest()->first())) {
                return $this->traceFabric($fabric);
            }
            return $this->notFound($q, 'Client PO found but no production started yet.');
        }

        return $this->notFound($q, 'No fabric, package, job order or PO matches. Try a control number in Lot mode.');
    }

    protected function notFound(string $q, string $hint): array
    {
        return ['found' => false, 'query' => $q, 'hint' => $hint];
    }

    // ─── Raw-lot trace ───────────────────────────────────────────────

    protected function traceLot(string $ctrl): array
    {
        $stock = WarehouseStockItem::with(['material', 'warehouse', 'receivedBy', 'section', 'shelf'])
            ->where('control_number', $ctrl)->first();

        if (! $stock) {
            return $this->notFound($ctrl, 'No warehouse stock with this control number.');
        }

        // Inbound: receiving record via material + date proximity is unreliable —
        // link through the SCM purchase order stamped on the stock item.
        $receiving = null;
        $invoice = null;
        $payment = null;
        $scmPo = $stock->purchase_order_id
            ? \App\Models\Scm\ScmPurchaseOrder::find($stock->purchase_order_id)
            : null;
        if ($scmPo) {
            $receiving = WarehouseReceiving::with(['items.material', 'warehouse'])
                ->where('scm_purchase_order_id', $scmPo->id)->latest('received_at')->first();
            $invoice = PurchaseInvoice::with('payments')->where('po_id', $scmPo->id)->latest()->first();
            $payment = $invoice?->payments?->sortByDesc('paid_date')->first();
        }

        // Release to production (same control number carried over).
        $release = ManufacturingInventoryItem::with('receivedFrom')
            ->where('control_number', $ctrl)->latest()->first();

        // Exact dye consumptions of this lot.
        $consumptions = DyeJobChemical::with(['dyeJob.fabric.salesOrder.client', 'dyeJob.operator'])
            ->where('control_number', $ctrl)->get()
            ->map(fn ($c) => [
                'dye_job' => $c->dyeJob?->code,
                'dye_type' => $c->dye_type,
                'qty_used' => $c->quantity_used,
                'fabric' => $c->dyeJob?->fabric?->code,
                'operator' => $c->dyeJob?->operator?->name,
                'processed_at' => $c->dyeJob?->processed_at,
                'client' => $c->dyeJob?->fabric?->salesOrder?->client?->company_name,
            ])->values();

        return [
            'found' => true,
            'kind' => 'lot',
            'query' => $ctrl,
            'summary' => [
                'material' => $stock->material?->name,
                'quantity' => $stock->quantity.' '.$stock->unit,
                'status' => $stock->status,
                'warehouse' => $stock->warehouse?->name,
                'location' => $stock->shelf ? 'Shelf '.$stock->shelf->shelf_number : ($stock->section ? 'Sector '.$stock->section->name : 'Unassigned'),
            ],
            'origin' => [
                'received_at' => $stock->received_at,
                'received_by' => $stock->receivedBy?->name,
                'scm_po' => $scmPo?->po_number,
                'supplier' => $scmPo?->supplier_name,
            ],
            'inbound' => $receiving ? [
                'receiving_number' => $receiving->receiving_number,
                'received_at' => $receiving->received_at,
                'warehouse' => $receiving->warehouse?->name,
                'status' => $receiving->status,
                'lines' => $receiving->items->map(fn ($i) => [
                    'material' => $i->material?->name, 'expected' => $i->expected_qty,
                    'received' => $i->received_qty, 'rejected' => $i->rejected_qty, 'status' => $i->status,
                ])->values(),
            ] : null,
            'billing' => $invoice ? [
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->amount,
                'status' => $invoice->status,
                'due_date' => $invoice->due_date,
                'paid_at' => $payment?->paid_date,
                'pay_method' => $payment?->method,
            ] : null,
            'release' => $release ? [
                'department' => $release->department,
                'qty' => $release->initial_quantity.' '.$release->unit,
                'remaining' => $release->remaining_quantity.' '.$release->unit,
                'released_by' => $release->receivedFrom?->name,
                'released_at' => $release->received_at,
                'status' => $release->status,
            ] : null,
            'consumptions' => $consumptions,
            'note' => $consumptions->isEmpty()
                ? 'Yarn lots link to finished fabrics through the knitting department release (no per-roll stamp); dye/chemical lots show exact dye-job consumption above.'
                : null,
        ];
    }

    // ─── Finished-unit trace ─────────────────────────────────────────

    protected function traceFabric(?Fabric $fabric): array
    {
        if (! $fabric) {
            return $this->notFound('', 'Fabric record missing.');
        }
        $fabric->load([
            'operator', 'machine', 'salesOrder.client', 'manufacturingOrder',
            'dyeJobs.operator', 'dyeJobs.machine', 'dyeJobs.chemicals',
            'softenerJobs.operator', 'softenerJobs.squeezerJob.operator',
            'softenerJobs.squeezerJob.ironJob.operator',
            'packages.operator',
        ]);

        $so = $fabric->salesOrder;
        $po = $so?->purchase_order_id
            ? PurchaseOrder::where('po_number', $so->purchase_order_id)->first()
            : null;

        // Forward chain: packages → warehouse packages → deliveries.
        $manPackages = $fabric->packages;
        $wpIds = WarehousePackage::where('manufacturing_order_id', $fabric->manufacturing_order_id)->pluck('id');
        $deliveries = $wpIds->isNotEmpty()
            ? Delivery::with(['truck', 'driver.user', 'route.client', 'proofOfDelivery'])
                ->whereHas('packages', fn ($q) => $q->whereIn('warehouse_package_id', $wpIds))
                ->get()->map(fn ($d) => [
                    'delivery_number' => $d->delivery_number,
                    'status' => $d->status,
                    'truck' => $d->truck ? trim(($d->truck->truck_number ?? '').' '.($d->truck->plate_number ?? '')) : '—',
                    'driver' => $d->driver?->user?->name ?? '—',
                    'route' => $d->route?->name ?? trim(($d->route?->origin ?? '').' → '.($d->route?->destination ?? '')),
                    'scheduled' => $d->scheduled_departure,
                    'departed' => $d->actual_departure,
                    'arrived' => $d->arrival_time,
                    'pod_at' => $d->proofOfDelivery?->delivered_at,
                    'client_confirmed' => str_contains((string) $d->notes, 'Client confirmed receipt'),
                ])->values()
            : collect();

        $stages = [];
        $stages[] = [
            'stage' => 'Knitting', 'code' => $fabric->code,
            'operator' => $fabric->operator?->name, 'machine' => $fabric->machine?->machine_no ?? $fabric->machine_id,
            'detail' => trim(($fabric->yarn_type ?? '').' · '.($fabric->weight ?? '').'kg'),
            'at' => $fabric->processed_at, 'status' => $fabric->status,
        ];
        foreach ($fabric->dyeJobs as $dj) {
            $stages[] = [
                'stage' => 'Dyeing', 'code' => $dj->code,
                'operator' => $dj->operator?->name, 'machine' => $dj->machine?->machine_no ?? $dj->machine_id,
                'detail' => trim(($dj->dye_type ?? '').($dj->chemical_no ? ' · lot '.$dj->chemical_no : '')),
                'lots' => $dj->chemicals->map(fn ($c) => ['lot' => $c->control_number, 'material' => $c->dye_type, 'used' => $c->quantity_used])->values(),
                'at' => $dj->processed_at, 'status' => null,
            ];
        }
        foreach ($fabric->softenerJobs as $sj) {
            $stages[] = [
                'stage' => 'Softener', 'code' => $sj->code,
                'operator' => $sj->operator?->name, 'machine' => $sj->machine?->machine_no ?? $sj->machine_id,
                'detail' => $sj->softener_type, 'at' => $sj->processed_at, 'status' => $sj->status,
            ];
            if ($sq = $sj->squeezerJob) {
                $stages[] = [
                    'stage' => 'Squeezer', 'code' => $sq->code,
                    'operator' => $sq->operator?->name, 'machine' => $sq->machine?->machine_no ?? $sq->machine_id,
                    'detail' => $sq->remarks, 'at' => $sq->processed_at, 'status' => null,
                ];
                if ($ir = $sq->ironJob) {
                    $stages[] = [
                        'stage' => 'Ironing', 'code' => $ir->code,
                        'operator' => $ir->operator?->name, 'machine' => null,
                        'detail' => $ir->remarks, 'at' => $ir->processed_at, 'status' => null,
                    ];
                }
            }
        }
        foreach ($manPackages as $pkg) {
            $stages[] = [
                'stage' => 'Packaging', 'code' => $pkg->code,
                'operator' => $pkg->operator?->name, 'machine' => null,
                'detail' => ($pkg->quantity ?? '').' pcs', 'at' => $pkg->packaged_at, 'status' => $pkg->status,
            ];
        }

        return [
            'found' => true,
            'kind' => 'unit',
            'query' => $fabric->code,
            'summary' => [
                'fabric' => $fabric->code,
                'yarn' => $fabric->yarn_type,
                'weight' => $fabric->weight,
                'status' => $fabric->status,
                'jo' => $so?->jo_number,
                'po' => $so?->purchase_order_id,
                'client' => $so?->client?->company_name,
                'ordered_total' => $so?->total_amount,
                'po_payment' => $po?->payment_status,
                'po_delivery' => $po?->delivery_date,
            ],
            'order' => $so ? [
                'client' => $so->client?->company_name,
                'jo' => $so->jo_number,
                'po' => $so->purchase_order_id,
                'qty' => $so->quantity,
                'total' => $so->total_amount,
                'created' => $so->created_at,
                'status' => $so->status,
            ] : null,
            'stages' => $stages,
            'deliveries' => $deliveries,
        ];
    }

    protected function orderOnlyTrace(SalesOrder $so): array
    {
        $so->load('client');
        $po = $so->purchase_order_id ? PurchaseOrder::where('po_number', $so->purchase_order_id)->first() : null;

        return [
            'found' => true,
            'kind' => 'order',
            'query' => $so->jo_number,
            'summary' => [
                'jo' => $so->jo_number,
                'po' => $so->purchase_order_id,
                'client' => $so->client?->company_name,
                'ordered_total' => $so->total_amount,
                'po_payment' => $po?->payment_status,
                'status' => $so->status,
            ],
            'order' => [
                'client' => $so->client?->company_name,
                'jo' => $so->jo_number,
                'po' => $so->purchase_order_id,
                'qty' => $so->quantity,
                'total' => $so->total_amount,
                'created' => $so->created_at,
                'status' => $so->status,
            ],
            'stages' => [],
            'deliveries' => [],
            'note' => 'Job order accepted but no fabrics produced yet — no process trail to show.',
        ];
    }
}
