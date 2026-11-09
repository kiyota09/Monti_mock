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

        $all = OrderReturn::with(['salesOrder.client', 'createdBy', 'resolvedBy', 'replacementSalesOrder'])
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
                'request_type' => $r->request_type,
                'package_ids' => $r->package_ids ?? [],
                'packages' => \App\Models\War\WarehousePackage::with('product')
                    ->whereIn('id', $r->package_ids ?? [])
                    ->get()
                    ->map(fn ($package) => ['number' => $package->package_number, 'product' => $package->product?->name]),
                'image_url' => $r->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($r->image_path) : null,
                'discount_amount' => (float) $r->discount_amount,
                'replacement_jo_number' => $r->replacementSalesOrder?->jo_number,
                'quantity' => (float) $r->quantity,
                'unit' => 'kg',
                'reason' => $r->reason,
                'status' => $r->status,
                'transitions' => OrderReturn::allowedTransitions($r->status),
                'created_by' => $r->client_id ? ($r->salesOrder?->client?->company_name ?? 'Partner client') : $r->createdBy?->name,
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

        $page = $request->routeIs('eco.returns')
            ? 'Dashboard/ECO/Returns'
            : 'Dashboard/ORD/Returns';

        return Inertia::render($page, [
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
            'status' => 'nullable|in:inspected,credited,rejected,closed',
            'decision' => 'nullable|in:replacement,price_adjustment,cancelled,rejected',
            'discount_amount' => 'nullable|numeric|min:0',
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        $ret = DB::transaction(function () use ($data, $id) {
            $ret = OrderReturn::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($ret->request_type) {
                if ($ret->status !== 'pending') {
                    throw ValidationException::withMessages(['status' => 'This partner request has already been reviewed.']);
                }
                $decision = $data['decision'] ?? '';
                if (! in_array($decision, ['replacement', 'price_adjustment', 'cancelled', 'rejected'], true)) {
                    throw ValidationException::withMessages(['decision' => 'Choose how to resolve this partner request.']);
                }
                if ($decision === 'rejected' && blank($data['resolution_notes'] ?? null)) {
                    throw ValidationException::withMessages(['resolution_notes' => 'A reason is required when rejecting a request.']);
                }

                $so = SalesOrder::whereKey($ret->sales_order_id)->lockForUpdate()->firstOrFail();
                if ($decision === 'replacement') {
                    $replacement = $so->replicate();
                    $replacement->jo_number = $this->lifecycle->nextJoNumber();
                    $replacement->control_number = $this->lifecycle->nextControlNumber($replacement->jo_number);
                    $replacement->quantity = $ret->quantity > 0 ? $ret->quantity : $so->quantity;
                    $scale = (float) $so->quantity > 0 ? (float) $replacement->quantity / (float) $so->quantity : 1;
                    $replacement->subtotal = round((float) $so->subtotal * $scale, 2);
                    $replacement->tax_amount = round((float) $so->tax_amount * $scale, 2);
                    $replacement->total_amount = round((float) $so->total_amount * $scale, 2);
                    $replacement->status = 'pending';
                    $replacement->pushed_to = null;
                    $replacement->expected_ship_date = null;
                    $replacement->confirmed_at = null;
                    $replacement->confirmed_by = null;
                    $replacement->production_started_at = null;
                    $replacement->production_done_at = null;
                    $replacement->delivered_at = null;
                    $replacement->cancel_reason = null;
                    $replacement->on_hold_reason = null;
                    $replacement->knitting_done_at = null;
                    $replacement->knitting_done_by = null;
                    $replacement->knitting_handoff_requested_at = null;
                    $replacement->knitting_handoff_requested_by = null;
                    $replacement->inv_check_sufficient = false;
                    $replacement->payment_status = 'unpaid';
                    $replacement->receipt_file = null;
                    $replacement->replacement_return_id = $ret->id;
                    $replacement->save();
                    $ret->replacement_sales_order_id = $replacement->id;
                } elseif (in_array($decision, ['price_adjustment', 'cancelled'], true)) {
                    $base = (float) $so->total_amount;
                    $discount = $decision === 'cancelled'
                        ? round($base * min(1, (float) $ret->quantity / max(0.01, (float) $so->quantity)), 2)
                        : (float) ($data['discount_amount'] ?? 0);
                    if ($decision === 'price_adjustment' && ($discount <= 0 || $discount > $base)) {
                        throw ValidationException::withMessages(['discount_amount' => 'Enter a discount greater than zero and no more than the order total.']);
                    }
                    $so->total_amount = round(max(0, $base - $discount), 2);
                    $taxMultiplier = 1 + ((float) $so->tax_rate / 100);
                    $so->subtotal = round($so->total_amount / max(1, $taxMultiplier), 2);
                    $so->tax_amount = round($so->total_amount - $so->subtotal, 2);
                    if ($decision === 'cancelled' && $so->total_amount <= 0) {
                        $so->status = 'cancelled';
                    }
                    $so->save();
                    $ret->discount_amount = $discount;
                }

                $ret->update([
                    'status' => $decision === 'rejected' ? 'rejected' : 'credited',
                    'resolution_notes' => $data['resolution_notes'] ?? ucfirst(str_replace('_', ' ', $decision)).' approved by ECO.',
                    'resolved_by' => auth()->id(),
                    'resolved_at' => now(),
                ]);
                return $ret;
            }

            if (empty($data['status'])) {
                throw ValidationException::withMessages(['status' => 'Choose a return status.']);
            }
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

        return back()->with('success', $ret->request_type
            ? "Request {$ret->return_number} resolved as ".str_replace('_', ' ', $data['decision']).'.'
            : "Return {$ret->return_number} marked {$data['status']}.");
    }
}
