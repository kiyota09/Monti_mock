<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Crm\Client;
use App\Models\Crm\CrmLead;
use App\Models\Fin\FinBill;
use App\Models\Fin\FinInvoice;
use App\Models\Fin\FinInvoicePayment;
use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\LeaveRequest;
use App\Models\Hrm\Payroll;
use App\Models\Logistics\Delivery;
use App\Models\Man\Fabric;
use App\Models\Man\Machine;
use App\Models\Man\SafetyIncident;
use App\Models\Ord\PurchaseOrder;
use App\Models\Ord\SalesOrder;
use App\Models\Pro\Supplier;
use App\Models\Pro\VendorRegistration;
use App\Models\Scm\MaterialRequest;
use App\Models\Scm\ScmPurchaseOrder;
use App\Services\Ord\OrderLifecycleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CeoDashboardController extends Controller
{
    /**
     * Executive command center for Monti Textile.
     *
     * A single read-only view of the whole textile value chain:
     * commercial (orders/revenue), plant (production/quality), supply
     * chain, finance (cash/AR/AP) and people — plus the decisions that
     * need the President's attention.
     */
    public function index()
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $prevStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $prevEnd = $prevStart->copy()->endOfMonth();

        // ---- Commercial -------------------------------------------------
        $salesOrders = SalesOrder::query();
        $revenueBooked = (float) (clone $salesOrders)->whereNotIn('status', ['cancelled'])->sum('total_amount');
        $revenueMtd = (float) (clone $salesOrders)->whereNotIn('status', ['cancelled'])
            ->whereBetween('created_at', [$monthStart, $now])->sum('total_amount');
        $revenuePrev = (float) (clone $salesOrders)->whereNotIn('status', ['cancelled'])
            ->whereBetween('created_at', [$prevStart, $prevEnd])->sum('total_amount');

        $openOrders = (clone $salesOrders)->whereNotIn('status', [
            'delivered', 'completed', 'cancelled',
        ])->count();
        $inProduction = (clone $salesOrders)->where('status', 'in_production')->count();
        $inProductionValue = (float) (clone $salesOrders)->where('status', 'in_production')->sum('total_amount');

        $leadsOpen = CrmLead::whereIn('status', ['Inquiry', 'Negotiation', 'Approval Sent'])->count();
        $leadsWon = CrmLead::whereIn('status', ['Closed-Won', 'Converted'])->count();
        $activeClients = Client::where('status', 'active')->count();

        // ---- Plant / production -----------------------------------------
        $outputMtd = Fabric::whereBetween('processed_at', [$monthStart, $now])->count();
        $outputPrev = Fabric::whereBetween('processed_at', [$prevStart, $prevEnd])->count();
        $outputWeightMtd = (float) Fabric::whereBetween('processed_at', [$monthStart, $now])->sum('weight');

        $fabricsTotal = Fabric::count();
        $rejectedFabrics = Fabric::where('status', 'rejected')->count();
        $rejectRate = $fabricsTotal > 0 ? round(($rejectedFabrics / $fabricsTotal) * 100, 1) : 0.0;
        $yieldRate = round(100 - $rejectRate, 1);

        $totalMachines = Machine::count();
        $availableMachines = Machine::where('status', 'available')->count();
        $downMachines = Machine::where('status', 'under_maintenance')
            ->orderByDesc('updated_at')->get();
        $machinesDown = $downMachines->count();
        $machineAvailability = $totalMachines > 0 ? round(($availableMachines / $totalMachines) * 100) : 0;

        $wip = Fabric::whereNotIn('status', ['packed', 'rejected'])->count();

        $stageCounts = Fabric::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $stages = collect([
            ['key' => 'pending', 'label' => 'Knitting Queue', 'color' => 'bg-sky-500'],
            ['key' => 'dyeing', 'label' => 'Dyeing', 'color' => 'bg-indigo-500'],
            ['key' => 'softener', 'label' => 'Softener', 'color' => 'bg-violet-500'],
            ['key' => 'squeezer', 'label' => 'Squeezer', 'color' => 'bg-fuchsia-500'],
            ['key' => 'iron', 'label' => 'Ironing', 'color' => 'bg-amber-500'],
            ['key' => 'forming', 'label' => 'Forming', 'color' => 'bg-teal-500'],
            ['key' => 'packed', 'label' => 'Packed / Ready', 'color' => 'bg-emerald-500'],
        ])->map(fn ($stage) => [
            'key' => $stage['key'],
            'label' => $stage['label'],
            'color' => $stage['color'],
            'count' => (int) ($stageCounts[$stage['key']] ?? 0),
        ])->values();

        // ---- Delivery ----------------------------------------------------
        $deliveries = Delivery::where('status', '!=', 'cancelled')->get();
        $delivered = $deliveries->where('status', 'delivered')->count();
        $scheduledDeliveries = $deliveries->filter(
            fn ($d) => $d->scheduled_departure && $d->actual_departure
        );
        $onTimeDepartures = $scheduledDeliveries->filter(
            fn ($d) => $d->actual_departure->lte($d->scheduled_departure)
        )->count();
        $onTimeRate = $scheduledDeliveries->count() > 0
            ? round(($onTimeDepartures / $scheduledDeliveries->count()) * 100)
            : ($deliveries->count() > 0 ? round(($delivered / $deliveries->count()) * 100) : 0);

        // ---- Finance -----------------------------------------------------
        $invoiceRows = FinInvoice::where('status', '!=', 'paid')->withSum('payments', 'amount')->get();
        $receivables = (float) $invoiceRows->sum(fn ($invoice) => max(
            0,
            (float) $invoice->amount - (float) ($invoice->payments_sum_amount ?? 0)
        ));
        $overdueInvoiceRows = $invoiceRows->filter(
            fn ($invoice) => $invoice->due_date && $invoice->due_date->isPast()
        )->sortBy('due_date')->values();
        $overdueReceivables = $overdueInvoiceRows->count();

        $billRows = FinBill::where('status', '!=', 'paid')->withSum('payments', 'amount')->get();
        $payables = (float) $billRows->sum(fn ($bill) => max(
            0,
            (float) $bill->amount - (float) ($bill->payments_sum_amount ?? 0)
        ));
        $overdueBillRows = $billRows->filter(
            fn ($bill) => $bill->due_date && $bill->due_date->isPast()
        )->sortBy('due_date')->values();
        $overduePayables = $overdueBillRows->count();

        $collectedMtd = (float) FinInvoicePayment::whereBetween('paid_at', [
            $monthStart->toDateString(), $now->toDateString(),
        ])->sum('amount');

        // ---- People ------------------------------------------------------
        $headcount = User::where('is_active', true)->count();
        $presentToday = AttendanceLog::whereDate('date', $now->toDateString())
            ->whereRaw('LOWER(status) != ?', ['absent'])->count();
        $pendingLeaves = LeaveRequest::where('status', 'pending')->count();

        // ---- Executive approvals ----------------------------------------
        $pendingPayrolls = Payroll::whereRaw('LOWER(status) = ?', ['pending'])
            ->latest()->get();
        $pendingVendorRows = VendorRegistration::where('status', 'pending')
            ->latest()->get();
        $pendingCreditRows = PurchaseOrder::with('client:id,company_name')
            ->where('status', 'credit_review')->latest()->get();
        $pendingPayroll = $pendingPayrolls->count();
        $pendingVendors = $pendingVendorRows->count();
        $pendingCredit = $pendingCreditRows->count();
        $pendingApprovals = $pendingPayroll + $pendingVendors + $pendingCredit;

        // ---- Supply chain -------------------------------------------------
        $procurementSpend = (float) ScmPurchaseOrder::sum('grand_total');
        $pendingRequests = MaterialRequest::where('status', 'pending')
            ->latest()->get();
        $pendingMaterialRequests = $pendingRequests->count();
        $approvedSuppliers = Supplier::where('status', 'approved')->count();

        $lowStock = DB::table('materials as m')
            ->leftJoin('warehouse_stock_items as s', function ($join) {
                $join->on('s.material_id', '=', 'm.id')
                    ->where('s.status', '=', 'in_stock');
            })
            ->select(
                'm.id',
                'm.mat_id',
                'm.name',
                'm.category',
                'm.unit',
                'm.reorder_point',
                'm.unit_cost',
                DB::raw('COALESCE(SUM(s.quantity), 0) as stock')
            )
            ->groupBy('m.id', 'm.mat_id', 'm.name', 'm.category', 'm.unit', 'm.reorder_point', 'm.unit_cost')
            ->havingRaw('COALESCE(SUM(s.quantity), 0) < m.reorder_point')
            ->orderBy('stock')
            ->get()
            ->map(fn ($material) => [
                'id' => $material->id,
                'mat_id' => $material->mat_id,
                'name' => $material->name,
                'category' => $material->category,
                'unit' => $material->unit,
                'unit_cost' => (float) $material->unit_cost,
                'reorder_point' => (float) $material->reorder_point,
                'stock' => (float) $material->stock,
            ])
            ->values();

        $openSafetyIncidents = SafetyIncident::with('operator:id,name')
            ->whereIn('status', ['open', 'investigating'])
            ->orderByDesc('incident_date')->get();
        $openIncidents = $openSafetyIncidents->count();
        $recentRejectedFabrics = Fabric::with('salesOrder:id,jo_number')
            ->where('status', 'rejected')
            ->where('updated_at', '>=', $now->copy()->subDays(30))
            ->orderByDesc('updated_at')->get();
        $rejectedLast30 = $recentRejectedFabrics->count();

        // ---- Trends (last 6 months) ---------------------------------------
        $labels = $revenueSeries = $spendSeries = $outputSeries = $rejectSeries = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonthsNoOverflow($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $labels[] = $month->format('M');

            $revenueSeries[] = (float) SalesOrder::whereNotIn('status', ['cancelled'])
                ->whereBetween('created_at', [$start, $end])->sum('total_amount');
            $spendSeries[] = (float) ScmPurchaseOrder::whereBetween('created_at', [$start, $end])
                ->sum('grand_total');
            $outputSeries[] = Fabric::whereBetween('processed_at', [$start, $end])->count();
            $rejectSeries[] = Fabric::where('status', 'rejected')
                ->whereBetween('updated_at', [$start, $end])->count();
        }

        // ---- Order pipeline (canonical textile order-to-cash stages) ------
        $pipelineLabels = [
            'intake' => 'Order Intake',
            'confirmed' => 'Planning',
            'production' => 'In Production',
            'ready' => 'Ready to Ship',
            'transit' => 'In Transit',
            'done' => 'Delivered',
            'attention' => 'On Hold / Returns',
        ];
        $pipeline = collect($pipelineLabels)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'count' => 0,
            'value' => 0.0,
        ])->keyBy('key');

        foreach (SalesOrder::select('status', DB::raw('COUNT(*) as total'), DB::raw('SUM(total_amount) as value'))
            ->whereNotIn('status', ['cancelled'])
            ->groupBy('status')
            ->get() as $row) {
            $group = OrderLifecycleService::soGroup($row->status);
            if (! $pipeline->has($group)) {
                continue;
            }
            $entry = $pipeline->get($group);
            $entry['count'] += (int) $row->total;
            $entry['value'] += (float) $row->value;
            $pipeline->put($group, $entry);
        }

        $pipelineTotal = max(1, $pipeline->sum('count'));
        $pipeline = $pipeline->values()->map(fn ($stage) => [
            ...$stage,
            'percent' => round(($stage['count'] / $pipelineTotal) * 100),
        ]);

        // ---- Top clients --------------------------------------------------
        $topClients = DB::table('sales_orders as so')
            ->join('clients as c', 'c.id', '=', 'so.client_id')
            ->whereNotIn('so.status', ['cancelled'])
            ->select(
                'c.company_name',
                DB::raw('COUNT(so.id) as orders'),
                DB::raw('SUM(so.total_amount) as revenue')
            )
            ->groupBy('c.id', 'c.company_name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($client) => [
                'name' => $client->company_name,
                'orders' => (int) $client->orders,
                'revenue' => (float) $client->revenue,
                'share' => $revenueBooked > 0
                    ? round(((float) $client->revenue / $revenueBooked) * 100, 1)
                    : 0,
            ])
            ->values();

        // ---- Recent orders ------------------------------------------------
        $recentOrders = SalesOrder::with('client')
            ->latest()
            ->take(6)
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'jo_number' => $order->jo_number,
                'client' => $order->client?->company_name ?? 'Unknown client',
                'color' => $order->color,
                'quantity' => (float) $order->quantity,
                'amount' => (float) $order->total_amount,
                'status' => $order->status,
                'status_label' => ucwords(str_replace('_', ' ', (string) $order->status)),
                'priority' => $order->priority ?? 'normal',
                'expected_ship_date' => $order->expected_ship_date?->format('M d, Y'),
                'created_at' => $order->created_at?->format('M d, Y'),
            ]);

        // ---- Priority action details (rendered in dashboard modals) -------
        $approvalRows = collect()
            ->concat($pendingPayrolls->map(fn ($payroll) => [
                'type' => 'Payroll',
                'reference' => 'Payroll #'.$payroll->id,
                'subject' => trim(($payroll->employee_name ?: 'Employee').' · '.($payroll->department ?: '—')),
                'amount' => $this->money((float) $payroll->net_pay),
                'submitted' => $payroll->created_at?->format('M d, Y') ?? '—',
            ]))
            ->concat($pendingVendorRows->map(fn ($vendor) => [
                'type' => 'Vendor registration',
                'reference' => $vendor->business_name,
                'subject' => trim(($vendor->representative_name ?: 'Representative').' · '.($vendor->email ?: '—')),
                'amount' => '—',
                'submitted' => $vendor->created_at?->format('M d, Y') ?? '—',
            ]))
            ->concat($pendingCreditRows->map(fn ($order) => [
                'type' => 'Credit review',
                'reference' => $order->po_number ?: 'PO #'.$order->id,
                'subject' => $order->client?->company_name ?? 'Unknown client',
                'amount' => $this->money((float) $order->total_amount),
                'submitted' => $order->created_at?->format('M d, Y') ?? '—',
            ]))
            ->values();

        $lowStockRows = $lowStock->map(function ($material) {
            $shortage = max(0, (float) $material['reorder_point'] - (float) $material['stock']);

            return [
                'material' => $material['name'],
                'category' => $material['category'] ?: '—',
                'on_hand' => number_format((float) $material['stock'], 2).' '.$material['unit'],
                'reorder_point' => number_format((float) $material['reorder_point'], 2).' '.$material['unit'],
                'shortage' => number_format($shortage, 2).' '.$material['unit'],
                'reorder_value' => (float) $material['unit_cost'] > 0
                    ? $this->money($shortage * (float) $material['unit_cost'])
                    : '—',
            ];
        })->values();
        $lowStockReorderValue = (float) $lowStock->sum(fn ($material) => max(
            0,
            (float) $material['reorder_point'] - (float) $material['stock']
        ) * (float) $material['unit_cost']);
        $largestLowStock = $lowStock->sortByDesc(
            fn ($material) => (float) $material['reorder_point'] - (float) $material['stock']
        )->first();
        $largestLowStockLabel = $largestLowStock
            ? $largestLowStock['name'].' · '.number_format(
                max(0, (float) $largestLowStock['reorder_point'] - (float) $largestLowStock['stock']), 2
            ).' '.$largestLowStock['unit']
            : '—';

        $receivableBalance = fn ($invoice) => max(
            0,
            (float) $invoice->amount - (float) ($invoice->payments_sum_amount ?? 0)
        );
        $overdueReceivableRows = $overdueInvoiceRows->map(fn ($invoice) => [
            'invoice' => $invoice->invoice_no,
            'client' => $invoice->client_name ?: '—',
            'due' => $invoice->due_date?->format('M d, Y') ?? '—',
            'days_late' => $this->daysLate($invoice->due_date).' days',
            'total' => $this->money((float) $invoice->amount),
            'paid' => $this->money((float) ($invoice->payments_sum_amount ?? 0)),
            'balance' => $this->money($receivableBalance($invoice)),
        ])->values();
        $overdueReceivableBalance = (float) $overdueInvoiceRows->sum($receivableBalance);
        $overdueReceivableClients = $overdueInvoiceRows->pluck('client_name')->filter()->unique()->count();
        $oldestReceivableDays = $overdueInvoiceRows->isEmpty()
            ? 0
            : (int) $overdueInvoiceRows->max(fn ($invoice) => $this->daysLate($invoice->due_date));

        $payableBalance = fn ($bill) => max(
            0,
            (float) $bill->amount - (float) ($bill->payments_sum_amount ?? 0)
        );
        $overduePayableRows = $overdueBillRows->map(fn ($bill) => [
            'bill' => $bill->bill_no,
            'supplier' => $bill->supplier_name ?: '—',
            'category' => $bill->category ?: '—',
            'due' => $bill->due_date?->format('M d, Y') ?? '—',
            'days_late' => $this->daysLate($bill->due_date).' days',
            'total' => $this->money((float) $bill->amount),
            'paid' => $this->money((float) ($bill->payments_sum_amount ?? 0)),
            'balance' => $this->money($payableBalance($bill)),
        ])->values();
        $overduePayableBalance = (float) $overdueBillRows->sum($payableBalance);
        $overduePayableSuppliers = $overdueBillRows->pluck('supplier_name')->filter()->unique()->count();
        $oldestPayableDays = $overdueBillRows->isEmpty()
            ? 0
            : (int) $overdueBillRows->max(fn ($bill) => $this->daysLate($bill->due_date));

        $downMachineRows = $downMachines->map(fn ($machine) => [
            'machine' => $machine->machine_no,
            'type' => ucfirst((string) $machine->type),
            'status' => ucwords(str_replace('_', ' ', (string) $machine->status)),
            'remarks' => $machine->remarks ?: '—',
            'updated' => $machine->updated_at?->format('M d, Y') ?? '—',
        ])->values();

        $urgencyRank = ['High' => 0, 'Medium' => 1, 'Low' => 2];
        $sortedPendingRequests = $pendingRequests
            ->sortBy(fn ($request) => $urgencyRank[$request->urgency] ?? 3)
            ->values();
        $materialRequestRows = $sortedPendingRequests->map(fn ($request) => [
            'request' => $request->req_number,
            'material' => $request->material_name,
            'category' => $request->category ?: '—',
            'required' => number_format((float) $request->required_qty, 2).' '.$request->unit,
            'on_hand' => number_format((float) $request->current_stock, 2).' '.$request->unit,
            'urgency' => ucfirst((string) $request->urgency),
            'requested_by' => $request->requested_by ?: '—',
            'requested' => $request->requested_at?->format('M d, Y') ?? '—',
            'notes' => $request->notes ?: '—',
        ])->values();

        $safetyRows = $openSafetyIncidents->map(fn ($incident) => [
            'code' => $incident->code,
            'type' => ucwords(str_replace('_', ' ', (string) $incident->incident_type)),
            'location' => $incident->location ?: '—',
            'department' => $incident->department ? ucfirst((string) $incident->department) : '—',
            'date' => $incident->incident_date?->format('M d, Y') ?? '—',
            'severity' => ucfirst((string) $incident->severity),
            'status' => ucfirst((string) $incident->status),
            'reported_by' => $incident->operator?->name ?? '—',
            'details' => trim(
                ($incident->description ?: '—')
                .($incident->corrective_action ? ' · Corrective action: '.$incident->corrective_action : '')
            ),
        ])->values();

        $qualityRows = $recentRejectedFabrics->map(fn ($fabric) => [
            'fabric' => $fabric->code,
            'yarn' => $fabric->yarn_type ?: '—',
            'weight' => number_format((float) $fabric->weight, 2).' kg',
            'reason' => $fabric->rejection_reason ?: '—',
            'action' => match ($fabric->rejection_action) {
                'recolor' => 'Recolor',
                'total' => 'Total loss',
                default => '—',
            },
            'order' => $fabric->salesOrder?->jo_number ?? '—',
            'rejected_on' => $fabric->updated_at?->format('M d, Y') ?? '—',
        ])->values();

        $priority = [
            'approvals' => [
                'title' => 'Decisions waiting for you',
                'subtitle' => 'Payroll, vendor registrations, and credit reviews awaiting the President.',
                'summary' => [
                    ['label' => 'Payroll', 'value' => number_format($pendingPayroll)],
                    ['label' => 'Vendors', 'value' => number_format($pendingVendors)],
                    ['label' => 'Credit reviews', 'value' => number_format($pendingCredit)],
                    ['label' => 'Total decisions', 'value' => number_format($pendingApprovals)],
                ],
                'columns' => [
                    ['key' => 'type', 'label' => 'Type'],
                    ['key' => 'reference', 'label' => 'Reference'],
                    ['key' => 'subject', 'label' => 'Subject'],
                    ['key' => 'amount', 'label' => 'Amount'],
                    ['key' => 'submitted', 'label' => 'Submitted'],
                ],
                'rows' => $approvalRows,
                'empty' => 'No decisions are waiting for approval.',
            ],
            'low-stock' => [
                'title' => 'Raw materials below reorder point',
                'subtitle' => 'Inventory items at or under their configured reorder threshold.',
                'summary' => [
                    ['label' => 'Materials', 'value' => number_format($lowStock->count())],
                    ['label' => 'Categories', 'value' => number_format($lowStock->pluck('category')->filter()->unique()->count())],
                    ['label' => 'Est. reorder cost', 'value' => $lowStockReorderValue > 0 ? $this->money($lowStockReorderValue) : '—'],
                    ['label' => 'Largest shortage', 'value' => $largestLowStockLabel],
                ],
                'columns' => [
                    ['key' => 'material', 'label' => 'Material', 'wrap' => true],
                    ['key' => 'category', 'label' => 'Category'],
                    ['key' => 'on_hand', 'label' => 'On hand'],
                    ['key' => 'reorder_point', 'label' => 'Reorder point'],
                    ['key' => 'shortage', 'label' => 'Shortage'],
                    ['key' => 'reorder_value', 'label' => 'Reorder value'],
                ],
                'rows' => $lowStockRows,
                'empty' => 'All materials are above their reorder point.',
            ],
            'ar-overdue' => [
                'title' => 'Overdue receivables',
                'subtitle' => 'Unpaid client invoices that are past their due date.',
                'summary' => [
                    ['label' => 'Invoices', 'value' => number_format($overdueReceivables)],
                    ['label' => 'Clients', 'value' => number_format($overdueReceivableClients)],
                    ['label' => 'Total balance', 'value' => $this->money($overdueReceivableBalance)],
                    ['label' => 'Oldest', 'value' => $oldestReceivableDays.' days'],
                ],
                'columns' => [
                    ['key' => 'invoice', 'label' => 'Invoice'],
                    ['key' => 'client', 'label' => 'Client', 'wrap' => true],
                    ['key' => 'due', 'label' => 'Due date'],
                    ['key' => 'days_late', 'label' => 'Days late'],
                    ['key' => 'total', 'label' => 'Total'],
                    ['key' => 'paid', 'label' => 'Paid'],
                    ['key' => 'balance', 'label' => 'Balance'],
                ],
                'rows' => $overdueReceivableRows,
                'empty' => 'No overdue receivables.',
            ],
            'machines-down' => [
                'title' => 'Machines under maintenance',
                'subtitle' => 'Production equipment currently unavailable for scheduling.',
                'summary' => [
                    ['label' => 'Machines down', 'value' => number_format($machinesDown)],
                    ['label' => 'Total machines', 'value' => number_format($totalMachines)],
                    ['label' => 'Availability', 'value' => $machineAvailability.'%'],
                    ['label' => 'Types affected', 'value' => number_format($downMachines->pluck('type')->filter()->unique()->count())],
                ],
                'columns' => [
                    ['key' => 'machine', 'label' => 'Machine'],
                    ['key' => 'type', 'label' => 'Type'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'remarks', 'label' => 'Remarks', 'wrap' => true],
                    ['key' => 'updated', 'label' => 'Last update'],
                ],
                'rows' => $downMachineRows,
                'empty' => 'All machines are available.',
            ],
            'material-requests' => [
                'title' => 'Material requests pending',
                'subtitle' => 'Procurement requests raised by inventory and production.',
                'summary' => [
                    ['label' => 'Requests', 'value' => number_format($pendingMaterialRequests)],
                    ['label' => 'High urgency', 'value' => number_format($pendingRequests->where('urgency', 'High')->count())],
                    ['label' => 'Medium', 'value' => number_format($pendingRequests->where('urgency', 'Medium')->count())],
                    ['label' => 'Low', 'value' => number_format($pendingRequests->where('urgency', 'Low')->count())],
                ],
                'columns' => [
                    ['key' => 'request', 'label' => 'Request #'],
                    ['key' => 'material', 'label' => 'Material', 'wrap' => true],
                    ['key' => 'category', 'label' => 'Category'],
                    ['key' => 'required', 'label' => 'Required'],
                    ['key' => 'on_hand', 'label' => 'On hand'],
                    ['key' => 'urgency', 'label' => 'Urgency'],
                    ['key' => 'requested_by', 'label' => 'Requested by'],
                    ['key' => 'requested', 'label' => 'Requested'],
                    ['key' => 'notes', 'label' => 'Notes', 'wrap' => true],
                ],
                'rows' => $materialRequestRows,
                'empty' => 'No pending material requests.',
            ],
            'ap-overdue' => [
                'title' => 'Supplier bills overdue',
                'subtitle' => 'Bills past their due date that still carry an unpaid balance.',
                'summary' => [
                    ['label' => 'Bills', 'value' => number_format($overduePayables)],
                    ['label' => 'Suppliers', 'value' => number_format($overduePayableSuppliers)],
                    ['label' => 'Total balance', 'value' => $this->money($overduePayableBalance)],
                    ['label' => 'Oldest', 'value' => $oldestPayableDays.' days'],
                ],
                'columns' => [
                    ['key' => 'bill', 'label' => 'Bill'],
                    ['key' => 'supplier', 'label' => 'Supplier', 'wrap' => true],
                    ['key' => 'category', 'label' => 'Category'],
                    ['key' => 'due', 'label' => 'Due date'],
                    ['key' => 'days_late', 'label' => 'Days late'],
                    ['key' => 'total', 'label' => 'Total'],
                    ['key' => 'paid', 'label' => 'Paid'],
                    ['key' => 'balance', 'label' => 'Balance'],
                ],
                'rows' => $overduePayableRows,
                'empty' => 'No overdue supplier bills.',
            ],
            'safety' => [
                'title' => 'Open safety incidents',
                'subtitle' => 'Safety reports under investigation or awaiting closure.',
                'summary' => [
                    ['label' => 'Open', 'value' => number_format($openSafetyIncidents->where('status', 'open')->count())],
                    ['label' => 'Investigating', 'value' => number_format($openSafetyIncidents->where('status', 'investigating')->count())],
                    ['label' => 'Critical / major', 'value' => number_format($openSafetyIncidents->whereIn('severity', ['critical', 'major'])->count())],
                    ['label' => 'Total incidents', 'value' => number_format($openIncidents)],
                ],
                'columns' => [
                    ['key' => 'code', 'label' => 'Incident'],
                    ['key' => 'type', 'label' => 'Type'],
                    ['key' => 'location', 'label' => 'Location'],
                    ['key' => 'department', 'label' => 'Dept.'],
                    ['key' => 'date', 'label' => 'Date'],
                    ['key' => 'severity', 'label' => 'Severity'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'reported_by', 'label' => 'Reported by'],
                    ['key' => 'details', 'label' => 'Details', 'wrap' => true],
                ],
                'rows' => $safetyRows,
                'empty' => 'No open safety incidents.',
            ],
            'quality' => [
                'title' => 'Fabric rejects (30 days)',
                'subtitle' => 'Rejected rolls recorded in the last 30 days, with disposition.',
                'summary' => [
                    ['label' => 'Rejects', 'value' => number_format($rejectedLast30)],
                    ['label' => 'For recolor', 'value' => number_format($recentRejectedFabrics->where('rejection_action', 'recolor')->count())],
                    ['label' => 'Total loss', 'value' => number_format($recentRejectedFabrics->where('rejection_action', 'total')->count())],
                    ['label' => 'Rejected weight', 'value' => number_format((float) $recentRejectedFabrics->sum('weight'), 2).' kg'],
                ],
                'columns' => [
                    ['key' => 'fabric', 'label' => 'Fabric'],
                    ['key' => 'yarn', 'label' => 'Yarn'],
                    ['key' => 'weight', 'label' => 'Weight'],
                    ['key' => 'reason', 'label' => 'Rejection reason', 'wrap' => true],
                    ['key' => 'action', 'label' => 'Disposition'],
                    ['key' => 'order', 'label' => 'Sales order'],
                    ['key' => 'rejected_on', 'label' => 'Rejected on'],
                ],
                'rows' => $qualityRows,
                'empty' => 'No fabric rejects in the last 30 days.',
            ],
        ];

        // ---- Executive alerts ---------------------------------------------
        $alerts = collect();

        if ($pendingApprovals > 0) {
            $alerts->push([
                'key' => 'approvals',
                'title' => 'Decisions waiting for you',
                'detail' => "{$pendingPayroll} payroll · {$pendingVendors} vendor · {$pendingCredit} credit",
                'count' => $pendingApprovals,
                'severity' => 'critical',
                'icon' => 'Stamp',
            ]);
        }

        if ($lowStock->isNotEmpty()) {
            $alerts->push([
                'key' => 'low-stock',
                'title' => 'Raw materials below reorder point',
                'detail' => $lowStock->take(3)->pluck('name')->implode(', '),
                'count' => $lowStock->count(),
                'severity' => 'warning',
                'icon' => 'PackageX',
            ]);
        }

        if ($overdueReceivables > 0) {
            $alerts->push([
                'key' => 'ar-overdue',
                'title' => 'Overdue receivables',
                'detail' => 'Invoices past their due date',
                'count' => $overdueReceivables,
                'severity' => 'critical',
                'icon' => 'AlertTriangle',
            ]);
        }

        if ($machinesDown > 0) {
            $alerts->push([
                'key' => 'machines-down',
                'title' => 'Machines under maintenance',
                'detail' => 'Production capacity is reduced',
                'count' => $machinesDown,
                'severity' => 'warning',
                'icon' => 'Wrench',
            ]);
        }

        if ($pendingMaterialRequests > 0) {
            $alerts->push([
                'key' => 'material-requests',
                'title' => 'Material requests pending',
                'detail' => 'Waiting for procurement action',
                'count' => $pendingMaterialRequests,
                'severity' => 'warning',
                'icon' => 'ClipboardList',
            ]);
        }

        if ($overduePayables > 0) {
            $alerts->push([
                'key' => 'ap-overdue',
                'title' => 'Supplier bills overdue',
                'detail' => 'Payables past their due date',
                'count' => $overduePayables,
                'severity' => 'warning',
                'icon' => 'Receipt',
            ]);
        }

        if ($openIncidents > 0) {
            $alerts->push([
                'key' => 'safety',
                'title' => 'Open safety incidents',
                'detail' => 'Open / under investigation',
                'count' => $openIncidents,
                'severity' => 'critical',
                'icon' => 'ShieldAlert',
            ]);
        }

        if ($rejectedLast30 > 0) {
            $alerts->push([
                'key' => 'quality',
                'title' => 'Fabric rejects (30 days)',
                'detail' => 'Quality holds needing disposition',
                'count' => $rejectedLast30,
                'severity' => 'info',
                'icon' => 'Layers',
            ]);
        }

        $alerts = $alerts->map(function ($alert) use ($priority) {
            $alert['modal'] = $priority[$alert['key']] ?? null;

            return $alert;
        })->sortBy(fn ($alert) => match ($alert['severity']) {
            'critical' => 0,
            'warning' => 1,
            default => 2,
        })->values();

        // ---- Balanced scorecard -------------------------------------------
        $departments = [
            [
                'key' => 'commercial',
                'title' => 'Commercial',
                'icon' => 'TrendingUp',
                'accent' => 'indigo',
                'metrics' => [
                    ['label' => 'Booked revenue', 'value' => $this->money($revenueBooked)],
                    ['label' => 'Open orders', 'value' => number_format($openOrders)],
                    ['label' => 'Active clients', 'value' => number_format($activeClients)],
                    ['label' => 'Leads open / won', 'value' => $leadsOpen.' / '.$leadsWon],
                ],
            ],
            [
                'key' => 'plant',
                'title' => 'Plant & Quality',
                'icon' => 'Factory',
                'accent' => 'emerald',
                'metrics' => [
                    ['label' => 'Output this month', 'value' => number_format($outputMtd).' rolls'],
                    ['label' => 'Yield', 'value' => $yieldRate.'%'],
                    ['label' => 'Machine availability', 'value' => $machineAvailability.'%'],
                    ['label' => 'Work in progress', 'value' => number_format($wip)],
                ],
            ],
            [
                'key' => 'supply',
                'title' => 'Supply Chain',
                'icon' => 'Boxes',
                'accent' => 'sky',
                'metrics' => [
                    ['label' => 'Procurement spend', 'value' => $this->money($procurementSpend)],
                    ['label' => 'Approved suppliers', 'value' => number_format($approvedSuppliers)],
                    ['label' => 'Pending requests', 'value' => number_format($pendingMaterialRequests)],
                    ['label' => 'Low stock items', 'value' => number_format($lowStock->count())],
                ],
            ],
            [
                'key' => 'finance',
                'title' => 'Finance & Cash',
                'icon' => 'Wallet',
                'accent' => 'amber',
                'metrics' => [
                    ['label' => 'Receivables', 'value' => $this->money($receivables)],
                    ['label' => 'Payables', 'value' => $this->money($payables)],
                    ['label' => 'Collected this month', 'value' => $this->money($collectedMtd)],
                    ['label' => 'Overdue invoices', 'value' => number_format($overdueReceivables)],
                ],
            ],
            [
                'key' => 'people',
                'title' => 'People',
                'icon' => 'Users',
                'accent' => 'violet',
                'metrics' => [
                    ['label' => 'Active headcount', 'value' => number_format($headcount)],
                    ['label' => 'Present today', 'value' => number_format($presentToday)],
                    ['label' => 'Pending leaves', 'value' => number_format($pendingLeaves)],
                    ['label' => 'Payroll approvals', 'value' => number_format($pendingPayroll)],
                ],
            ],
        ];

        return Inertia::render('Dashboard/CEO/Index', [
            'generatedAt' => $now->format('F d, Y · g:i A'),
            'periodLabel' => $now->format('F Y'),
            'kpis' => [
                'revenue' => [
                    'booked' => $revenueBooked,
                    'mtd' => $revenueMtd,
                    'growth' => $this->growth($revenueMtd, $revenuePrev),
                ],
                'orders' => [
                    'open' => $openOrders,
                    'in_production' => $inProduction,
                    'in_production_value' => $inProductionValue,
                ],
                'production' => [
                    'output_mtd' => $outputMtd,
                    'output_weight' => $outputWeightMtd,
                    'growth' => $this->growth($outputMtd, $outputPrev),
                ],
                'quality' => [
                    'yield' => $yieldRate,
                    'reject_rate' => $rejectRate,
                    'rejected' => $rejectedFabrics,
                    'total' => $fabricsTotal,
                ],
                'delivery' => [
                    'on_time' => $onTimeRate,
                    'delivered' => $delivered,
                    'active' => $deliveries->count() - $delivered,
                ],
                'finance' => [
                    'receivables' => $receivables,
                    'payables' => $payables,
                    'collected_mtd' => $collectedMtd,
                    'overdue_receivables' => $overdueReceivables,
                    'net_position' => $receivables - $payables,
                ],
                'people' => [
                    'headcount' => $headcount,
                    'present_today' => $presentToday,
                    'pending_leaves' => $pendingLeaves,
                ],
                'approvals' => [
                    'total' => $pendingApprovals,
                    'payroll' => $pendingPayroll,
                    'vendors' => $pendingVendors,
                    'credit' => $pendingCredit,
                ],
            ],
            'trends' => [
                'labels' => $labels,
                'revenue' => $revenueSeries,
                'spend' => $spendSeries,
                'output' => $outputSeries,
                'rejects' => $rejectSeries,
            ],
            'plant' => [
                'available_machines' => $availableMachines,
                'total_machines' => $totalMachines,
                'machine_availability' => $machineAvailability,
                'wip' => $wip,
            ],
            'commercial' => [
                'active_clients' => $activeClients,
                'leads_open' => $leadsOpen,
                'leads_won' => $leadsWon,
            ],
            'procurementSpend' => $procurementSpend,
            'pipeline' => $pipeline,
            'stages' => $stages,
            'topClients' => $topClients,
            'recentOrders' => $recentOrders,
            'alerts' => $alerts,
            'departments' => $departments,
            'lowStock' => $lowStock->take(4)->values(),
        ]);
    }

    /**
     * Month-over-month growth percentage, or null when there is no base.
     */
    private function growth(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Whole days between a due date and today, never negative.
     */
    private function daysLate(?Carbon $dueDate): int
    {
        if (! $dueDate) {
            return 0;
        }

        return max(0, (int) $dueDate->copy()->startOfDay()->diffInDays(now()->startOfDay()));
    }

    private function money(float $amount): string
    {
        return '₱'.number_format($amount, 0);
    }
}
