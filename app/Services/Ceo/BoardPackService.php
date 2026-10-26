<?php

namespace App\Services\Ceo;

use App\Models\Ceo\ExecutiveActionLog;
use App\Models\Core\User;
use App\Models\Crm\Client;
use App\Models\Crm\CrmLead;
use App\Models\Fin\FinBill;
use App\Models\Fin\FinBillPayment;
use App\Models\Fin\FinExpense;
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

/**
 * Builds the monthly board pack: a single, board-ready view of the
 * textile value chain (finance, commercial, plant, supply, people and
 * risk) for the period to date.
 *
 * Every figure is read-only and derived from live module data. Revenue is
 * recognised from booked sales orders (cancelled excluded); procurement
 * from SCM purchase orders; payables/receivables from open finance
 * documents net of recorded payments.
 */
class BoardPackService
{
    public function build(): array
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $prevStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $prevEnd = $prevStart->copy()->endOfMonth();
        $yearStart = $now->copy()->startOfYear();

        $monthly = $this->monthlySeries($now, 12);
        $last = count($monthly) - 1;
        $prev = max(0, $last - 1);

        // ---- Financial performance -------------------------------------
        $revenueMtd = (float) $monthly[$last]['revenue'];
        $revenuePrev = (float) $monthly[$prev]['revenue'];
        $procurementMtd = (float) $monthly[$last]['spend'];
        $procurementPrev = (float) $monthly[$prev]['spend'];
        $payrollMtd = (float) $monthly[$last]['payroll'];
        $payrollPrev = (float) $monthly[$prev]['payroll'];
        $outputMtd = (int) $monthly[$last]['output'];
        $outputPrev = (int) $monthly[$prev]['output'];
        $rejectsMtd = (int) $monthly[$last]['rejects'];

        $revenueYtd = (float) SalesOrder::whereNotIn('status', ['cancelled'])
            ->whereBetween('created_at', [$yearStart, $now])->sum('total_amount');
        $revenueBooked = (float) SalesOrder::whereNotIn('status', ['cancelled'])->sum('total_amount');
        $procurementYtd = (float) ScmPurchaseOrder::whereBetween('created_at', [$yearStart, $now])
            ->sum('grand_total');
        $procurementTotal = (float) ScmPurchaseOrder::sum('grand_total');
        $payrollYtd = (float) Payroll::whereBetween('created_at', [$yearStart, $now])->sum('net_pay');
        $payrollTotal = (float) Payroll::sum('net_pay');
        $expensesMtd = (float) FinExpense::whereBetween('expense_date', [
            $monthStart->toDateString(), $monthEnd->toDateString(),
        ])->sum('amount');

        $grossProfit = $revenueMtd - $procurementMtd;
        $grossMargin = $revenueMtd > 0 ? round(($grossProfit / $revenueMtd) * 100, 1) : 0.0;
        $netResult = $revenueMtd - $procurementMtd - $payrollMtd - $expensesMtd;

        // Receivables / payables (open balances, net of payments).
        $invoiceRows = FinInvoice::withSum('payments', 'amount')->get();
        $invoiceBalance = fn ($invoice) => max(
            0,
            (float) $invoice->amount - (float) ($invoice->payments_sum_amount ?? 0)
        );
        $receivables = (float) $invoiceRows->sum($invoiceBalance);

        $billRows = FinBill::withSum('payments', 'amount')->get();
        $billBalance = fn ($bill) => max(
            0,
            (float) $bill->amount - (float) ($bill->payments_sum_amount ?? 0)
        );
        $payables = (float) $billRows->sum($billBalance);

        $overdueInvoices = $invoiceRows->filter(
            fn ($invoice) => $invoiceBalance($invoice) > 0
                && $invoice->due_date && $invoice->due_date->isPast()
        );
        $overdueBills = $billRows->filter(
            fn ($bill) => $billBalance($bill) > 0
                && $bill->due_date && $bill->due_date->isPast()
        );
        $overdueReceivables = (float) $overdueInvoices->sum($invoiceBalance);
        $overduePayables = (float) $overdueBills->sum($billBalance);
        $oldestReceivable = $this->oldestDays($overdueInvoices, $now);
        $oldestPayable = $this->oldestDays($overdueBills, $now);

        $arAging = $this->aging($invoiceRows, $invoiceBalance, $now);
        $apAging = $this->aging($billRows, $billBalance, $now);

        $collectedMtd = (float) FinInvoicePayment::whereBetween('paid_at', [
            $monthStart->toDateString(), $now->toDateString(),
        ])->sum('amount');
        $supplierPaidMtd = (float) FinBillPayment::whereBetween('paid_at', [
            $monthStart->toDateString(), $now->toDateString(),
        ])->sum('amount');

        // ---- Commercial --------------------------------------------------
        $pipeline = $this->pipeline();
        $topClients = $this->topClients($revenueBooked);
        $openOrders = (int) SalesOrder::whereNotIn('status', ['delivered', 'completed', 'cancelled'])->count();
        $inProduction = (int) SalesOrder::where('status', 'in_production')->count();
        $inProductionValue = (float) SalesOrder::where('status', 'in_production')->sum('total_amount');
        $backlogValue = (float) collect($pipeline)
            ->whereIn('key', ['intake', 'confirmed', 'production', 'ready'])
            ->sum('value');
        $activeClients = (int) Client::where('status', 'active')->count();
        $leadsOpen = (int) CrmLead::whereIn('status', ['Inquiry', 'Negotiation', 'Approval Sent'])->count();
        $leadsWon = (int) CrmLead::whereIn('status', ['Closed-Won', 'Converted'])->count();
        $concentration = $topClients[0]['share'] ?? 0;

        // ---- Plant & quality ----------------------------------------------
        $fabricsTotal = (int) Fabric::count();
        $rejectedTotal = (int) Fabric::where('status', 'rejected')->count();
        $periodRejectRate = $outputMtd > 0 ? round(($rejectsMtd / $outputMtd) * 100, 1) : 0.0;
        $yieldRate = round(100 - $periodRejectRate, 1);
        $outputWeight = (float) Fabric::whereBetween('processed_at', [$monthStart, $now])->sum('weight');
        $wip = (int) Fabric::whereNotIn('status', ['packed', 'rejected'])->count();
        $stageCounts = Fabric::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->pluck('total', 'status');
        $stages = collect([
            ['key' => 'pending', 'label' => 'Knitting Queue'],
            ['key' => 'dyeing', 'label' => 'Dyeing'],
            ['key' => 'softener', 'label' => 'Softener'],
            ['key' => 'squeezer', 'label' => 'Squeezer'],
            ['key' => 'iron', 'label' => 'Ironing'],
            ['key' => 'forming', 'label' => 'Forming'],
            ['key' => 'packed', 'label' => 'Packed / Ready'],
        ])->map(fn ($stage) => [
            'key' => $stage['key'],
            'label' => $stage['label'],
            'count' => (int) ($stageCounts[$stage['key']] ?? 0),
        ])->all();

        $totalMachines = (int) Machine::count();
        $availableMachines = (int) Machine::where('status', 'available')->count();
        $machinesDown = (int) Machine::where('status', 'under_maintenance')->count();
        $machineAvailability = $totalMachines > 0
            ? (int) round(($availableMachines / $totalMachines) * 100)
            : 0;

        $deliveries = Delivery::where('status', '!=', 'cancelled')->get();
        $delivered = (int) $deliveries->where('status', 'delivered')->count();
        $inTransit = (int) $deliveries->whereIn('status', ['dispatched', 'in_transit'])->count();
        $scheduled = $deliveries->filter(fn ($d) => $d->scheduled_departure && $d->actual_departure);
        $onTime = $scheduled->filter(fn ($d) => $d->actual_departure->lte($d->scheduled_departure))->count();
        $onTimeRate = $scheduled->count() > 0
            ? (int) round(($onTime / $scheduled->count()) * 100)
            : ($deliveries->count() > 0 ? (int) round(($delivered / $deliveries->count()) * 100) : 0);

        // ---- Supply chain ---------------------------------------------------
        $approvedSuppliers = (int) Supplier::where('status', 'approved')->count();
        $openPurchaseOrders = (int) ScmPurchaseOrder::whereNotIn('status', [
            'delivered', 'received', 'completed', 'cancelled',
        ])->count();
        $pendingRequests = (int) MaterialRequest::where('status', 'pending')->count();
        $openRequests = (int) MaterialRequest::whereNotIn('status', [
            'fulfilled', 'completed', 'cancelled', 'rejected', 'closed',
        ])->count();
        $lowStock = $this->lowStock();
        $lowStockReorderValue = (float) $lowStock->sum(fn ($material) => max(
            0,
            $material['reorder_point'] - $material['stock']
        ) * $material['unit_cost']);
        $topSuppliers = ScmPurchaseOrder::select(
            'supplier_name',
            DB::raw('COUNT(*) as orders'),
            DB::raw('SUM(grand_total) as spend')
        )
            ->groupBy('supplier_name')
            ->orderByDesc('spend')
            ->limit(5)
            ->get()
            ->map(fn ($supplier) => [
                'name' => $supplier->supplier_name ?: 'Unknown supplier',
                'orders' => (int) $supplier->orders,
                'spend' => (float) $supplier->spend,
                'share' => $procurementTotal > 0
                    ? round(((float) $supplier->spend / $procurementTotal) * 100, 1)
                    : 0,
            ])->all();

        // ---- People ---------------------------------------------------------
        $headcount = (int) User::where('is_active', true)->count();
        $presentToday = (int) AttendanceLog::whereDate('date', $now->toDateString())
            ->whereRaw('LOWER(status) != ?', ['absent'])->count();
        $attendanceRate = $headcount > 0 ? (int) round(($presentToday / $headcount) * 100) : 0;
        $pendingLeaves = (int) LeaveRequest::where('status', 'pending')->count();
        $payrollPending = (int) Payroll::whereRaw('LOWER(status) = ?', ['pending'])->count();
        $departments = DB::table('users')
            ->where('is_active', true)
            ->selectRaw('department, COUNT(*) as total')
            ->groupBy('department')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'department' => $row->department ?: 'Unassigned',
                'total' => (int) $row->total,
                'share' => $headcount > 0 ? round(((int) $row->total / $headcount) * 100, 1) : 0,
            ])->all();

        // ---- Executive decisions & approvals --------------------------------
        $decisions = ExecutiveActionLog::with('actor:id,name')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'date' => $log->created_at?->format('M d, Y') ?? '—',
                'action' => ucwords(str_replace('_', ' ', (string) $log->action_type)),
                'subject' => $log->subject_label,
                'decision' => ucfirst((string) $log->decision),
                'reason' => $log->reason,
                'actor' => $log->actor?->name ?? 'System',
            ])->values()->all();

        $pendingPayroll = (int) Payroll::whereRaw('LOWER(status) = ?', ['pending'])->count();
        $pendingVendors = (int) VendorRegistration::where('status', 'pending')->count();
        $pendingCredit = (int) PurchaseOrder::where('status', 'credit_review')->count();
        $pendingApprovals = $pendingPayroll + $pendingVendors + $pendingCredit;
        $pendingPayrollValue = (float) Payroll::whereRaw('LOWER(status) = ?', ['pending'])->sum('net_pay');

        $openIncidents = (int) SafetyIncident::whereIn('status', ['open', 'investigating'])->count();
        $criticalIncidents = (int) SafetyIncident::whereIn('status', ['open', 'investigating'])
            ->whereIn('severity', ['critical', 'major'])->count();

        // ---- Risk register ---------------------------------------------------
        $workingCapital = $receivables - $payables;
        $risks = $this->risks([
            'overdue_receivables' => $overdueReceivables,
            'overdue_invoices' => $overdueInvoices->count(),
            'oldest_receivable' => $oldestReceivable,
            'overdue_payables' => $overduePayables,
            'overdue_bills' => $overdueBills->count(),
            'oldest_payable' => $oldestPayable,
            'working_capital' => $workingCapital,
            'low_stock' => $lowStock->count(),
            'machines_down' => $machinesDown,
            'machine_availability' => $machineAvailability,
            'open_incidents' => $openIncidents,
            'critical_incidents' => $criticalIncidents,
            'reject_rate' => $periodRejectRate,
            'rejects_mtd' => $rejectsMtd,
            'pending_approvals' => $pendingApprovals,
            'pending_payroll' => $pendingPayroll,
            'pending_vendors' => $pendingVendors,
            'pending_credit' => $pendingCredit,
            'pending_requests' => $pendingRequests,
        ]);

        $boardActions = $this->boardActions($risks, $pendingApprovals);

        return [
            'meta' => [
                'company' => 'Monti Textile Manufacturing Corp.',
                'title' => 'Monthly Board Pack',
                'ref' => 'MONTI-BP-'.$now->format('Ym'),
                'period' => $now->format('F Y'),
                'period_range' => $monthStart->format('M d').' – '.$monthEnd->format('M d, Y'),
                'generated_at' => $now->format('F d, Y · g:i A'),
                'prepared_by' => auth()->user()?->name ?? 'Office of the President',
                'prepared_for' => 'Board of Directors',
                'classification' => 'Confidential — Board Use Only',
                'meeting' => 'Regular Board Meeting',
                'fiscal_year' => 'FY '.$now->year,
            ],
            'financial' => [
                'revenue' => [
                    'mtd' => $revenueMtd,
                    'prev' => $revenuePrev,
                    'ytd' => $revenueYtd,
                    'booked' => $revenueBooked,
                    'growth' => $this->growth($revenueMtd, $revenuePrev),
                ],
                'procurement' => [
                    'mtd' => $procurementMtd,
                    'prev' => $procurementPrev,
                    'ytd' => $procurementYtd,
                    'total' => $procurementTotal,
                    'growth' => $this->growth($procurementMtd, $procurementPrev),
                ],
                'payroll' => [
                    'mtd' => $payrollMtd,
                    'prev' => $payrollPrev,
                    'ytd' => $payrollYtd,
                    'total' => $payrollTotal,
                    'growth' => $this->growth($payrollMtd, $payrollPrev),
                ],
                'expenses_mtd' => $expensesMtd,
                'gross_profit' => $grossProfit,
                'gross_margin' => $grossMargin,
                'net_result' => $netResult,
                'receivables' => $receivables,
                'payables' => $payables,
                'overdue_receivables' => $overdueReceivables,
                'overdue_payables' => $overduePayables,
                'overdue_invoice_count' => $overdueInvoices->count(),
                'overdue_bill_count' => $overdueBills->count(),
                'oldest_receivable' => $oldestReceivable,
                'oldest_payable' => $oldestPayable,
                'collected_mtd' => $collectedMtd,
                'supplier_paid_mtd' => $supplierPaidMtd,
                'working_capital' => $workingCapital,
                'ar_aging' => $arAging,
                'ap_aging' => $apAging,
                'monthly' => $monthly,
            ],
            'commercial' => [
                'active_clients' => $activeClients,
                'leads_open' => $leadsOpen,
                'leads_won' => $leadsWon,
                'open_orders' => $openOrders,
                'in_production' => $inProduction,
                'in_production_value' => $inProductionValue,
                'backlog_value' => $backlogValue,
                'concentration' => $concentration,
                'top_clients' => $topClients,
                'pipeline' => $pipeline,
            ],
            'operations' => [
                'output_mtd' => $outputMtd,
                'output_prev' => $outputPrev,
                'output_growth' => $this->growth($outputMtd, $outputPrev),
                'output_weight' => $outputWeight,
                'fabrics_total' => $fabricsTotal,
                'rejected_total' => $rejectedTotal,
                'rejects_mtd' => $rejectsMtd,
                'reject_rate' => $periodRejectRate,
                'yield_rate' => $yieldRate,
                'wip' => $wip,
                'machines_total' => $totalMachines,
                'machines_available' => $availableMachines,
                'machines_down' => $machinesDown,
                'machine_availability' => $machineAvailability,
                'deliveries_total' => $deliveries->count(),
                'delivered' => $delivered,
                'in_transit' => $inTransit,
                'on_time_rate' => $onTimeRate,
                'stages' => $stages,
            ],
            'supply' => [
                'procurement_mtd' => $procurementMtd,
                'procurement_ytd' => $procurementYtd,
                'approved_suppliers' => $approvedSuppliers,
                'open_purchase_orders' => $openPurchaseOrders,
                'pending_requests' => $pendingRequests,
                'open_requests' => $openRequests,
                'low_stock_count' => $lowStock->count(),
                'low_stock_reorder_value' => $lowStockReorderValue,
                'low_stock' => $lowStock->take(10)->values()->all(),
                'top_suppliers' => $topSuppliers,
            ],
            'people' => [
                'headcount' => $headcount,
                'present_today' => $presentToday,
                'attendance_rate' => $attendanceRate,
                'pending_leaves' => $pendingLeaves,
                'payroll_mtd' => $payrollMtd,
                'payroll_ytd' => $payrollYtd,
                'payroll_pending' => $payrollPending,
                'departments' => $departments,
            ],
            'approvals' => [
                'total' => $pendingApprovals,
                'payroll' => $pendingPayroll,
                'vendors' => $pendingVendors,
                'credit' => $pendingCredit,
                'payroll_value' => $pendingPayrollValue,
                'material_requests' => $pendingRequests,
            ],
            'risks' => $risks,
            'board_actions' => $boardActions,
            'decisions' => $decisions,
        ];
    }

    /**
     * Twelve-month operating series aligned to calendar months.
     */
    private function monthlySeries(Carbon $now, int $months): array
    {
        $series = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = $now->copy()->subMonthsNoOverflow($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $revenue = (float) SalesOrder::whereNotIn('status', ['cancelled'])
                ->whereBetween('created_at', [$start, $end])->sum('total_amount');
            $spend = (float) ScmPurchaseOrder::whereBetween('created_at', [$start, $end])
                ->sum('grand_total');
            $payroll = (float) Payroll::whereBetween('created_at', [$start, $end])->sum('net_pay');
            $output = (int) Fabric::whereBetween('processed_at', [$start, $end])->count();
            $rejects = (int) Fabric::where('status', 'rejected')
                ->whereBetween('updated_at', [$start, $end])->count();

            $series[] = [
                'label' => $month->format('M Y'),
                'short' => $month->format('M'),
                'revenue' => $revenue,
                'spend' => $spend,
                'payroll' => $payroll,
                'gross' => $revenue - $spend,
                'output' => $output,
                'rejects' => $rejects,
            ];
        }

        return $series;
    }

    /**
     * Canonical textile order-to-cash pipeline (orders and value).
     */
    private function pipeline(): array
    {
        $labels = [
            'intake' => 'Order Intake',
            'confirmed' => 'Planning',
            'production' => 'In Production',
            'ready' => 'Ready to Ship',
            'transit' => 'In Transit',
            'done' => 'Delivered',
            'attention' => 'On Hold / Returns',
        ];

        $pipeline = collect($labels)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'count' => 0,
            'value' => 0.0,
            'percent' => 0,
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

        $total = max(1, $pipeline->sum('count'));

        return $pipeline->values()->map(fn ($stage) => [
            ...$stage,
            'percent' => (int) round(($stage['count'] / $total) * 100),
        ])->all();
    }

    private function topClients(float $bookedRevenue): array
    {
        return DB::table('sales_orders as so')
            ->join('clients as c', 'c.id', '=', 'so.client_id')
            ->whereNotIn('so.status', ['cancelled'])
            ->select('c.company_name', DB::raw('COUNT(so.id) as orders'), DB::raw('SUM(so.total_amount) as revenue'))
            ->groupBy('c.id', 'c.company_name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($client) => [
                'name' => $client->company_name,
                'orders' => (int) $client->orders,
                'revenue' => (float) $client->revenue,
                'share' => $bookedRevenue > 0
                    ? round(((float) $client->revenue / $bookedRevenue) * 100, 1)
                    : 0,
            ])->all();
    }

    private function lowStock()
    {
        return DB::table('materials as m')
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
                'category' => $material->category ?: '—',
                'unit' => $material->unit,
                'unit_cost' => (float) $material->unit_cost,
                'reorder_point' => (float) $material->reorder_point,
                'stock' => (float) $material->stock,
                'shortage' => max(0, (float) $material->reorder_point - (float) $material->stock),
            ]);
    }

    /**
     * Outstanding balance buckets, signed days past due.
     */
    private function aging($rows, callable $balance, Carbon $today): array
    {
        $buckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '90_plus' => 0.0];
        $counts = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];

        foreach ($rows as $row) {
            $value = $balance($row);
            if ($value <= 0) {
                continue;
            }

            $days = $row->due_date
                ? (int) $row->due_date->copy()->startOfDay()->diffInDays($today->copy()->startOfDay(), false)
                : 0;

            $key = match (true) {
                $days <= 0 => 'current',
                $days <= 30 => '1_30',
                $days <= 60 => '31_60',
                $days <= 90 => '61_90',
                default => '90_plus',
            };

            $buckets[$key] += $value;
            $counts[$key]++;
        }

        return [
            'amounts' => $buckets,
            'counts' => $counts,
            'count' => array_sum($counts),
            'total' => array_sum($buckets),
        ];
    }

    private function oldestDays($rows, Carbon $today): int
    {
        $oldest = 0;

        foreach ($rows as $row) {
            if (! $row->due_date) {
                continue;
            }
            $days = (int) $row->due_date->copy()->startOfDay()->diffInDays($today->copy()->startOfDay(), false);
            $oldest = max($oldest, $days);
        }

        return $oldest;
    }

    /**
     * Material risk register derived from live exceptions.
     */
    private function risks(array $f): array
    {
        $risks = [];

        if ($f['overdue_receivables'] > 0) {
            $risks[] = [
                'area' => 'Credit & receivables',
                'description' => $this->plural($f['overdue_invoices'], 'client invoice').' past due, aged up to '
                    .$this->plural($f['oldest_receivable'], 'day').'.',
                'exposure' => $this->money($f['overdue_receivables']),
                'severity' => $f['oldest_receivable'] > 60 || $f['overdue_receivables'] > 100000 ? 'critical' : 'high',
                'owner' => 'Finance / Credit',
                'mitigation' => 'Escalate collection follow-ups and hold further credit releases for delinquent accounts.',
            ];
        }

        if ($f['overdue_payables'] > 0) {
            $risks[] = [
                'area' => 'Supplier payables',
                'description' => $this->plural($f['overdue_bills'], 'supplier bill').' past due, aged up to '
                    .$this->plural($f['oldest_payable'], 'day').'.',
                'exposure' => $this->money($f['overdue_payables']),
                'severity' => $f['oldest_payable'] > 60 ? 'critical' : 'high',
                'owner' => 'Finance / Treasury',
                'mitigation' => 'Approve a dated payment schedule and confirm continuity of material supply.',
            ];
        }

        if ($f['working_capital'] < 0) {
            $risks[] = [
                'area' => 'Working capital',
                'description' => 'Open payables exceed open receivables, producing a negative working-capital position.',
                'exposure' => $this->money(abs($f['working_capital'])),
                'severity' => 'high',
                'owner' => 'Finance / Treasury',
                'mitigation' => 'Review funding facilities, supplier terms and collection acceleration with the board.',
            ];
        }

        if ($f['low_stock'] > 0) {
            $risks[] = [
                'area' => 'Raw material availability',
                'description' => $this->plural($f['low_stock'], 'material').' below the reorder point, with '
                    .$this->plural($f['pending_requests'], 'request').' awaiting procurement action.',
                'exposure' => $this->plural($f['low_stock'], 'item'),
                'severity' => $f['low_stock'] >= 5 ? 'high' : 'medium',
                'owner' => 'Supply Chain',
                'mitigation' => 'Approve replenishment and confirm supplier lead times against the production plan.',
            ];
        }

        if ($f['machines_down'] > 0) {
            $risks[] = [
                'area' => 'Plant capacity',
                'description' => $this->plural($f['machines_down'], 'machine').' under maintenance; availability at '
                    .$f['machine_availability'].'%.',
                'exposure' => $f['machine_availability'].'% availability',
                'severity' => $f['machine_availability'] < 80 ? 'high' : 'medium',
                'owner' => 'Plant / Maintenance',
                'mitigation' => 'Approve the maintenance recovery plan and re-sequence affected production orders.',
            ];
        }

        if ($f['open_incidents'] > 0) {
            $risks[] = [
                'area' => 'Health & safety',
                'description' => $this->plural($f['open_incidents'], 'safety incident').' remain open or under investigation.',
                'exposure' => $f['critical_incidents'].' critical / major',
                'severity' => $f['critical_incidents'] > 0 ? 'critical' : 'high',
                'owner' => 'Safety / Compliance',
                'mitigation' => 'Track corrective actions to closure and report outcomes at the next board meeting.',
            ];
        }

        if ($f['reject_rate'] > 0) {
            $risks[] = [
                'area' => 'Product quality',
                'description' => $this->plural($f['rejects_mtd'], 'roll').' rejected this period at a '
                    .$f['reject_rate'].'% reject rate.',
                'exposure' => $f['reject_rate'].'% rejects',
                'severity' => $f['reject_rate'] > 5 ? 'high' : 'medium',
                'owner' => 'Plant / Quality',
                'mitigation' => 'Approve corrective actions on dyeing and finishing controls; monitor yield weekly.',
            ];
        }

        if ($f['pending_approvals'] > 0) {
            $risks[] = [
                'area' => 'Governance',
                'description' => $this->plural($f['pending_approvals'], 'executive approval').' pending: '
                    .$f['pending_payroll'].' payroll, '.$f['pending_vendors'].' vendor, '
                    .$f['pending_credit'].' credit.',
                'exposure' => $this->plural($f['pending_approvals'], 'item'),
                'severity' => 'medium',
                'owner' => 'Office of the President',
                'mitigation' => 'Resolve pending approvals to prevent payroll and procurement delays.',
            ];
        }

        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

        usort($risks, fn ($a, $b) => $rank[$a['severity']] <=> $rank[$b['severity']]);

        return $risks;
    }

    private function boardActions(array $risks, int $pendingApprovals): array
    {
        $actions = [];

        foreach ($risks as $risk) {
            if (count($actions) >= 6) {
                break;
            }

            $actions[] = [
                'title' => match ($risk['area']) {
                    'Credit & receivables' => 'Approve collections escalation for overdue accounts',
                    'Supplier payables' => 'Approve supplier payment schedule',
                    'Working capital' => 'Note funding and working-capital plan',
                    'Raw material availability' => 'Approve replenishment of low-stock materials',
                    'Plant capacity' => 'Approve maintenance recovery plan',
                    'Health & safety' => 'Review open safety corrective actions',
                    'Product quality' => 'Approve quality improvement actions',
                    'Governance' => 'Release pending executive approvals',
                    default => 'Review '.$risk['area'],
                },
                'owner' => $risk['owner'],
                'priority' => $risk['severity'],
                'metric' => $risk['exposure'],
            ];
        }

        if ($pendingApprovals > 0 && ! collect($actions)->contains(fn ($a) => str_contains($a['title'], 'pending executive'))) {
            $actions[] = [
                'title' => 'Release pending executive approvals',
                'owner' => 'Office of the President',
                'priority' => 'medium',
                'metric' => $this->plural($pendingApprovals, 'item'),
            ];
        }

        return $actions;
    }

    private function growth(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function plural(int $count, string $singular): string
    {
        return $count.' '.($count === 1 ? $singular : $singular.'s');
    }

    private function money(float $amount): string
    {
        return ($amount < 0 ? '-' : '').'₱'.number_format(abs($amount), 0);
    }
}
