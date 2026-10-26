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
     * ORD command center: KPIs, sales pipeline funnel, attention queue,
     * receivables and recent audit activity.
     */
    public function index()
    {
        $so = SalesOrder::query();
        $po = PurchaseOrder::query();

        $openSo = (clone $so)->whereNotIn('status', OrderLifecycleService::TERMINAL)->count();
        $openPo = (clone $po)->whereNotIn('status', OrderLifecycleService::TERMINAL)->count();

        $funnel = [
            'intake' => 0, 'confirmed' => 0, 'production' => 0,
            'ready' => 0, 'transit' => 0, 'done' => 0,
            'attention' => 0, 'cancelled' => 0,
        ];
        foreach ((clone $so)->pluck('status') as $status) {
            $group = OrderLifecycleService::soGroup($status);
            $funnel[$group] = ($funnel[$group] ?? 0) + 1;
        }

        $soAttention = SalesOrder::with('client')
            ->where(function ($q) {
                $q->whereIn('status', ['on_hold', 'returned'])
                    ->orWhere(function ($inner) {
                        $inner->whereNotIn('status', [...OrderLifecycleService::TERMINAL, 'on_hold', 'returned'])
                            ->whereNotNull('expected_ship_date')
                            ->whereDate('expected_ship_date', '<', now()->toDateString());
                    });
            })
            ->get()
            ->map(fn ($o) => $this->soRow($o));

        $poAttention = PurchaseOrder::with('client')
            ->where(function ($q) {
                $q->where('status', 'on_hold')
                    ->orWhere(function ($inner) {
                        $inner->whereNotIn('status', OrderLifecycleService::TERMINAL)
                            ->whereNotNull('expected_ship_date')
                            ->whereDate('expected_ship_date', '<', now()->toDateString());
                    });
            })
            ->get()
            ->map(fn ($o) => $this->poRow($o));

        $attention = $soAttention
            ->merge($poAttention)
            ->sortBy('expected_ship_date')
            ->take(10)
            ->values();

        $revenueMtd = (clone $so)
            ->whereIn('status', ['delivered', 'completed'])
            ->whereYear('updated_at', now()->year)
            ->whereMonth('updated_at', now()->month)
            ->sum('total_amount');

        $unpaidTotal = (clone $so)->whereIn('payment_status', ['unpaid', 'partially_paid'])
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount')
            + (clone $po)->whereIn('payment_status', ['unpaid', 'partially_paid'])
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        $openReturns = OrderReturn::whereNotIn('status', ['credited', 'rejected', 'closed'])->count();

        $activity = OrderStatusHistory::with(['changedBy', 'orderable'])
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'order_type' => $h->order_type,
                'order_id' => $h->order_id,
                'number' => $this->activityNumber($h),
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

    private function activityNumber(OrderStatusHistory $history): ?string
    {
        if (! $history->orderable) {
            return null;
        }

        return $history->order_type === 'PO'
            ? $history->orderable->po_number
            : $history->orderable->jo_number;
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

    private function poRow($o): array
    {
        return [
            'id' => $o->id,
            'type' => 'PO',
            'number' => $o->po_number,
            'client_name' => $o->client?->company_name ?? 'N/A',
            'status' => $o->status,
            'total' => (float) $o->total_amount,
            'expected_ship_date' => $o->expected_ship_date?->format('Y-m-d'),
            'priority' => $o->priority ?? 'normal',
        ];
    }
}
