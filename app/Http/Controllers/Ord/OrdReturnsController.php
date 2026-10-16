<?php

namespace App\Http\Controllers\Ord;

use App\Http\Controllers\Controller;
use App\Models\Ord\OrderReturn;
use App\Models\Ord\SalesOrder;
use App\Services\Ord\OrderLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OrdReturnsController extends Controller
{
    public function __construct(private OrderLifecycleService $lifecycle) {}

    public function index()
    {
        $returns = OrderReturn::with(['salesOrder.client', 'createdBy', 'resolvedBy'])
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'return_number' => $r->return_number,
                'jo_number' => $r->salesOrder?->jo_number ?? 'N/A',
                'client_name' => $r->salesOrder?->client?->company_name ?? 'N/A',
                'type' => $r->type,
                'quantity' => (float) $r->quantity,
                'reason' => $r->reason,
                'status' => $r->status,
                'created_by' => $r->createdBy?->name,
                'resolved_by' => $r->resolvedBy?->name,
                'resolved_at' => $r->resolved_at?->format('Y-m-d H:i'),
                'resolution_notes' => $r->resolution_notes,
                'created_at' => $r->created_at?->format('Y-m-d H:i'),
            ]);

        $deliveredOrders = SalesOrder::with('client')
            ->whereIn('status', ['delivered', 'completed', 'in_transit'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn ($so) => [
                'id' => $so->id,
                'jo_number' => $so->jo_number,
                'client_name' => $so->client?->company_name ?? 'N/A',
                'quantity' => (float) $so->quantity,
            ]);

        return Inertia::render('Dashboard/ORD/Returns', [
            'returns' => $returns,
            'types' => OrderReturn::TYPES,
            'statuses' => OrderReturn::STATUSES,
            'deliveredOrders' => $deliveredOrders,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sales_order_id' => 'required|exists:sales_orders,id',
            'type' => 'required|in:shortage,reject,client_return,overrun',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:2000',
        ]);

        $so = SalesOrder::findOrFail($data['sales_order_id']);

        if ($data['quantity'] > (float) $so->quantity) {
            return back()->withErrors(['error' => 'Return quantity cannot exceed the ordered quantity.']);
        }

        $ret = DB::transaction(function () use ($data, $so) {
            $ret = OrderReturn::create([
                'return_number' => $this->lifecycle->nextReturnNumber(),
                'sales_order_id' => $so->id,
                'type' => $data['type'],
                'quantity' => $data['quantity'],
                'reason' => $data['reason'],
                'status' => 'pending',
                'created_by' => auth()->id(),
            ]);

            // A return pulls the order back into the attention lane.
            if ($so->status === 'delivered'
                && in_array('returned', OrderLifecycleService::allowedSoTransitions($so->status), true)) {
                $this->lifecycle->transitionSalesOrder($so, 'returned', auth()->user(), "Return {$ret->return_number} filed");
            }

            return $ret;
        });

        return back()->with('success', "Return {$ret->return_number} filed against {$so->jo_number}.");
    }

    public function resolve(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|in:inspected,credited,rejected,closed',
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        $ret = OrderReturn::findOrFail($id);

        $ret->update([
            'status' => $data['status'],
            'resolution_notes' => $data['resolution_notes'] ?? null,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        return back()->with('success', "Return {$ret->return_number} marked {$data['status']}.");
    }
}
