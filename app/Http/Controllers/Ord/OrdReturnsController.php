<?php

namespace App\Http\Controllers\Ord;

use App\Http\Controllers\Controller;
use App\Models\Ord\OrderReturn;
use App\Models\Ord\SalesOrder;
use App\Services\Ord\OrderLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrdReturnsController extends Controller
{
    public function __construct(private OrderLifecycleService $lifecycle) {}

    public function index(Request $request)
    {
        $filters = [
            'status' => (string) $request->get('status', ''),
            'type' => (string) $request->get('type', ''),
            'search' => trim((string) $request->get('search', '')),
        ];

        $all = OrderReturn::with(['salesOrder.client', 'createdBy', 'resolvedBy'])
            ->latest()
            ->get();

        $counts = ['all' => $all->count()];
        foreach ($all->groupBy('status') as $status => $group) {
            $counts[$status] = $group->count();
        }

        $returns = $all
            ->when($filters['status'] !== '', fn ($c) => $c->where('status', $filters['status']))
            ->when($filters['type'] !== '', fn ($c) => $c->where('type', $filters['type']))
            ->when($filters['search'] !== '', function ($c) use ($filters) {
                $needle = strtolower($filters['search']);

                return $c->filter(fn ($r) => str_contains(strtolower((string) $r->return_number), $needle)
                    || str_contains(strtolower((string) $r->salesOrder?->jo_number), $needle)
                    || str_contains(strtolower((string) $r->salesOrder?->client?->company_name), $needle));
            })
            ->values()
            ->map(fn ($r) => [
                'id' => $r->id,
                'return_number' => $r->return_number,
                'sales_order_id' => $r->sales_order_id,
                'jo_number' => $r->salesOrder?->jo_number ?? 'N/A',
                'client_name' => $r->salesOrder?->client?->company_name ?? 'N/A',
                'type' => $r->type,
                'quantity' => (float) $r->quantity,
                'unit' => 'kg',
                'reason' => $r->reason,
                'status' => $r->status,
                'transitions' => OrderReturn::allowedTransitions($r->status),
                'created_by' => $r->createdBy?->name,
                'resolved_by' => $r->resolvedBy?->name,
                'resolved_at' => $r->resolved_at?->format('Y-m-d H:i'),
                'resolution_notes' => $r->resolution_notes,
                'created_at' => $r->created_at?->format('Y-m-d H:i'),
            ]);

        $deliveredOrders = SalesOrder::with('client')
            ->whereIn('status', ['delivered', 'completed', 'returned'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn ($so) => [
                'id' => $so->id,
                'jo_number' => $so->jo_number,
                'client_name' => $so->client?->company_name ?? 'N/A',
                'quantity' => (float) $so->quantity,
                'unit' => 'kg',
            ]);

        return Inertia::render('Dashboard/ORD/Returns', [
            'returns' => $returns,
            'counts' => $counts,
            'filters' => $filters,
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
            'quantity' => 'required|numeric|decimal:0,2|min:0.01',
            'reason' => 'required|string|max:2000',
        ]);

        $result = DB::transaction(function () use ($data) {
            // Lock the parent order so simultaneous return claims cannot
            // both pass the remaining-quantity check.
            $so = SalesOrder::whereKey($data['sales_order_id'])->lockForUpdate()->firstOrFail();
            if (! in_array($so->status, ['delivered', 'completed', 'returned'], true)) {
                throw ValidationException::withMessages(['error' => 'Returns can only be filed against delivered or completed job orders.']);
            }

            $alreadyReturned = $so->returns()
                ->whereNotIn('status', ['rejected', 'closed'])
                ->sum('quantity');
            if (($alreadyReturned + (float) $data['quantity']) > (float) $so->quantity + 0.001) {
                throw ValidationException::withMessages(['quantity' => 'Return quantity exceeds the remaining returnable quantity for this job order.']);
            }

            $ret = OrderReturn::create([
                'return_number' => $this->lifecycle->nextReturnNumber(),
                'sales_order_id' => $so->id,
                'type' => $data['type'],
                'quantity' => $data['quantity'],
                'reason' => $data['reason'],
                'status' => 'pending',
                'created_by' => auth()->id(),
            ]);

            if ($so->status === 'delivered'
                && in_array('returned', OrderLifecycleService::allowedSoTransitions($so->status), true)) {
                $this->lifecycle->transitionSalesOrder($so, 'returned', auth()->user(), "Return {$ret->return_number} filed");
            }

            return ['return' => $ret, 'jo_number' => $so->jo_number];
        }, 3);

        return back()->with('success', "Return {$result['return']->return_number} filed against {$result['jo_number']}.");
    }

    /**
     * Advance an RMA through its allowed lifecycle only. Rejected claims must
     * carry a resolution note so the client-facing audit trail is complete.
     */
    public function resolve(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|in:inspected,credited,rejected,closed',
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        $ret = DB::transaction(function () use ($data, $id) {
            $ret = OrderReturn::whereKey($id)->lockForUpdate()->firstOrFail();
            $allowed = OrderReturn::allowedTransitions($ret->status);
            if (! in_array($data['status'], $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot move {$ret->return_number} from {$ret->status} to {$data['status']}.",
                ]);
            }

            if ($data['status'] === 'rejected' && blank($data['resolution_notes'] ?? null)) {
                throw ValidationException::withMessages(['resolution_notes' => 'A rejection reason is required.']);
            }

            $final = in_array($data['status'], ['credited', 'rejected', 'closed'], true);
            $ret->update([
                'status' => $data['status'],
                'resolution_notes' => $data['resolution_notes'] ?? $ret->resolution_notes,
                'resolved_by' => $final ? auth()->id() : $ret->resolved_by,
                'resolved_at' => $final ? now() : $ret->resolved_at,
            ]);

            return $ret;
        }, 3);

        return back()->with('success', "Return {$ret->return_number} marked {$data['status']}.");
    }
}
