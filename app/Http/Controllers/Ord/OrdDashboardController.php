<?php

namespace App\Http\Controllers\Ord;

use App\Http\Controllers\Controller;
use App\Models\Ord\OrderReturn;
use App\Models\Ord\OrderStatusHistory;
use App\Models\Ord\PurchaseOrder;
use App\Models\Ord\SalesOrder;
use App\Services\Ord\OrderLifecycleService;
use Inertia\Inertia;

class OrdDashboardController extends Controller
{
    /**
     * ORD command center: KPIs, pipeline funnel, attention queue, revenue.
     */
    public function index()
    {
        $so = SalesOrder::query();
        $po = PurchaseOrder::query();

        $openSo = (clone $so)->whereNotIn('status', [...OrderLifecycleService::TERMINAL])->count();
        $openPo = (clone $po)->whereNotIn('status', [...OrderLifecycleService::TERMINAL])->count();

        // Funnel counts (canonical groups)
        $funnel = [
            'intake' => 0, 'confirmed' => 0, 'production' => 0,
            'ready' => 0, 'transit' => 0, 'done' => 0,
            'attention' => 0, 'cancelled' => 0,
        ];
        foreach ((clone $so)->pluck('status') as $status) {
            $group = OrderLifecycleService::soGroup($status);
            $funnel[$group] = ($funnel[$group] ?? 0) + 1;
        }

        // Attention queue: on hold / returned / overdue (past expected ship date, not terminal)
        $attention = SalesOrder::with('client')
            ->where(function ($q) {
                $q->whereIn('status', ['on_hold', 'returned'])
                    ->orWhere(function ($q) {
                        $q->whereNotIn('status', [...OrderLifecycleService::TERMINAL, 'on_hold', 'returned'])
                            ->whereNotNull('expected_ship_date')
                            ->whereDate('expected_ship_date', '<', now()->toDateString());
                    });
            })
            ->orderBy('expected_ship_date')
            ->limit(10)
            ->get()
            ->map(fn ($o) => $this->soRow($o));

        // Revenue: completed/delivered SOs this month + paid POs
        $revenueMtd = (clone $so)
            ->whereIn('status', ['delivered', 'completed'])
            ->whereYear('updated_at', now()->year)
            ->whereMonth('updated_at', now()->month)
            ->sum('total_amount');

        $unpaidTotal = (clone $so)->where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled'])
            ->sum('total_amount')
            + (clone $po)->where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled'])
            ->sum('total_amount');

        $openReturns = OrderReturn::whereNotIn('status', ['credited', 'rejected', 'closed'])->count();

        // Recent lifecycle activity
        $activity = OrderStatusHistory::with('changedBy')
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'order_type' => $h->order_type,
                'order_id' => $h->order_id,
                'from' => $h->from_status,
                'to' => $h->to_status,
                'by' => $h->changedBy?->name ?? 'System',
                'notes' => $h->notes,
                'at' => $h->created_at?->format('M d, H:i'),
            ]);

        return Inertia::render('Dashboard/ORD/Index', [
            'kpis' => [
                'open_orders' => $openSo + $openPo,
                'open_sales' => $openSo,
                'open_purchase' => $openPo,
                'in_production' => $funnel['production'],
                'ready_transit' => $funnel['ready'] + $funnel['transit'],
                'attention' => $funnel['attention'],
                'open_returns' => $openReturns,
                'revenue_mtd' => (float) $revenueMtd,
                'unpaid_total' => (float) $unpaidTotal,
            ],
            'funnel' => $funnel,
            'attention' => $attention,
            'activity' => $activity,
        ]);
    }

    private function soRow($o): array
    {
        return [
            'id' => $o->id,
            'type' => 'SO',
            'number' => $o->jo_number,
            'client_name' => $o->client?->company_name ?? 'N/A',
            'status' => $o->status,
            'quantity' => (float) $o->quantity,
            'total' => (float) $o->total_amount,
            'expected_ship_date' => $o->expected_ship_date?->format('Y-m-d'),
            'priority' => $o->priority ?? 'normal',
        ];
    }
}
