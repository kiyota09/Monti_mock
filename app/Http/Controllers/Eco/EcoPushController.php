<?php

namespace App\Http\Controllers\Eco;

use App\Http\Controllers\Controller;
use App\Models\Core\Notification;
use App\Models\Core\User;
use App\Models\Ord\OrderQueue;
use App\Models\Ord\PurchaseOrder;
use App\Models\Crm\Client;
use App\Models\Ord\SalesOrder;
use App\Services\Eco\StockSustainabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EcoPushController extends Controller
{
    /**
     * Display the Push Center Dashboard.
     */
    public function index()
    {
        // ── 1. Pending pushes ────────────────────────────────────────────────
        $salesOrders = SalesOrder::where('sales_orders.status', 'pending')

            // Client resolution (direct or via PO)
            ->leftJoin('clients as direct_client', 'sales_orders.client_id', '=', 'direct_client.id')
            ->leftJoin('purchase_orders', 'sales_orders.purchase_order_id', '=', 'purchase_orders.po_number')
            ->leftJoin('clients as po_client', 'purchase_orders.client_id', '=', 'po_client.id')

            // Join bom_records to get recipe details
            ->leftJoin('bom_records', 'sales_orders.recipe_id', '=', 'bom_records.id')

            ->select(
                'sales_orders.*',
                'bom_records.id as recipe_id',
                'direct_client.company_name   as direct_company_name',
                'po_client.company_name       as po_company_name',
                'bom_records.yarn_type        as recipe_yarn_type',
                'bom_records.dye_color        as recipe_dye_color',
                'bom_records.weave_design     as recipe_weave_design',
            )
            ->latest('sales_orders.created_at')
            ->get()
            ->map(function ($order) {
                $data = $order->toArray();
                $companyName = $order->direct_company_name ?? $order->po_company_name ?? 'N/A';
                $data['client'] = ['company_name' => $companyName];
                // Resolve the owning client id (direct FK wins, else via PO number).
                $clientId = $order->client_id
                    ?? PurchaseOrder::where('po_number', $order->purchase_order_id)->value('client_id');
                $data['client']['id'] = $clientId;
                $data['credit'] = $this->creditFor($clientId, $order->total_amount);

                $recipeId = $order->recipe_id ?? null;
                if ($recipeId) $recipeId = (int) $recipeId;

                $data['extracted_recipes'] = $recipeId ? [$recipeId] : [];
                $data['recipe'] = $recipeId ? [
                    'id'           => $recipeId,
                    'yarn_type'    => $order->recipe_yarn_type    ?? null,
                    'dye_color'    => $order->recipe_dye_color    ?? null,
                    'weave_design' => $order->recipe_weave_design ?? null,
                ] : null;

                return $data;
            });

        // ── 2. Already-pushed orders ────────────────────────────────────────
        $pushedOrders = SalesOrder::where('sales_orders.status', '!=', 'pending')
            ->leftJoin('clients as direct_client', 'sales_orders.client_id', '=', 'direct_client.id')
            ->leftJoin('purchase_orders', 'sales_orders.purchase_order_id', '=', 'purchase_orders.po_number')
            ->leftJoin('clients as po_client', 'purchase_orders.client_id', '=', 'po_client.id')
            ->leftJoin('bom_records', 'sales_orders.recipe_id', '=', 'bom_records.id')
            ->select(
                'sales_orders.*',
                'bom_records.id as recipe_id',
                'direct_client.company_name   as direct_company_name',
                'po_client.company_name       as po_company_name',
                'bom_records.yarn_type        as recipe_yarn_type',
                'bom_records.dye_color        as recipe_dye_color',
                'bom_records.weave_design     as recipe_weave_design',
            )
            ->latest('sales_orders.updated_at')
            ->get()
            ->map(function ($order) {
                $data = $order->toArray();
                $companyName = $order->direct_company_name ?? $order->po_company_name ?? 'N/A';
                $data['client'] = ['company_name' => $companyName];
                $clientId = $order->client_id
                    ?? PurchaseOrder::where('po_number', $order->purchase_order_id)->value('client_id');
                $data['client']['id'] = $clientId;
                $data['credit'] = $this->creditFor($clientId, $order->total_amount);

                $recipeId = $order->recipe_id ?? null;
                if ($recipeId) $recipeId = (int) $recipeId;

                $data['extracted_recipes'] = $recipeId ? [$recipeId] : [];
                $data['recipe'] = $recipeId ? [
                    'id'           => $recipeId,
                    'yarn_type'    => $order->recipe_yarn_type    ?? null,
                    'dye_color'    => $order->recipe_dye_color    ?? null,
                    'weave_design' => $order->recipe_weave_design ?? null,
                ] : null;

                return $data;
            });

        return Inertia::render('Dashboard/ECO/Push', [
            'salesOrders'  => $salesOrders,
            'pushedOrders' => $pushedOrders,
        ]);
    }

    /**
     * Credit snapshot for the Push Center confirm modal + Credit ledger
     * alignment. Advisory only — Monti may still accept at their judgement
     * even when the client is flagged At Risk / has pending balances.
     */
    protected function creditFor($clientId, $orderAmount = 0): array
    {
        if (!$clientId) {
            return ['is_good_payer' => true, 'outstanding_balance' => 0, 'pending_count' => 0, 'over_limit' => false];
        }
        $client = Client::with('creditAccount')->find($clientId);
        $outstanding = (float) ($client?->creditAccount?->outstanding_balance ?? 0);
        $isGood = $client?->creditAccount ? (bool) $client->creditAccount->is_good_payer : true;
        $pending = PurchaseOrder::where('client_id', $clientId)
            ->whereIn('status', ['credit_review', 'pending_client_approval'])
            ->count();
        $overLimit = $client && $client->credit_limit
            ? ($outstanding + (float) $orderAmount) > (float) $client->credit_limit
            : false;
        return [
            'is_good_payer' => $isGood,
            'outstanding_balance' => $outstanding,
            'pending_count' => $pending,
            'over_limit' => $overLimit,
            'credit_limit' => $client?->credit_limit,
        ];
    }

    /**
     * DSS preview — quick sustainability check for one pending order.
     * Returns JSON: per-material required / committed / on-hand / ATP /
     * shortage plus the overall verdict. Used by the Push Center modal
     * BEFORE ECO accepts the order.
     */
    public function dssCheck($order, StockSustainabilityService $dss)
    {
        $salesOrder = SalesOrder::findOrFail($order);

        return response()->json($dss->evaluate($salesOrder));
    }

    /**
     * DSS modal "Request" button: file a procurement request for ONE
     * specific shortfall material and notify the procurement (PRO) module
     * staff to process it. Idempotent per job order + material.
     */
    public function requestProcurement($order, Request $request, StockSustainabilityService $dss)
    {
        $salesOrder = SalesOrder::findOrFail($order);

        if ($salesOrder->status !== 'pending') {
            return response()->json(['message' => 'Only pending orders can request procurement.'], 422);
        }

        $data = $request->validate([
            'material_id' => 'required|exists:materials,id',
        ]);

        $result = $dss->requestMaterial($salesOrder, (int) $data['material_id'], auth()->user()?->name);

        if (! ($result['requested'] ?? false)) {
            return response()->json([
                'message' => $result['message'] ?? 'No request needed.',
                'reason' => $result['reason'] ?? null,
            ], 422);
        }

        // Notify the procurement module: every active PRO account gets a
        // bell notification linking straight to Material Requests. The
        // request itself flows through the normal pipeline
        // (SCM Procurement Orders → forwarded → PRO).
        $jo = $result['jo_number'] ?? $salesOrder->jo_number ?? ('JO-'.$salesOrder->id);
        $proUsers = User::where('role', 'PRO')->where('is_active', true)->get(['id']);
        foreach ($proUsers as $pro) {
            Notification::notify(
                (int) $pro->id,
                'procurement',
                "Procurement requested: {$result['material_name']} for {$jo}",
                "Short {$result['shortage']}{$result['unit']} (needs {$result['required']}{$result['unit']}, ATP {$result['atp']}{$result['unit']}). Request {$result['req_number']} filed from the ECO Push Center.",
                'pro.manager.material-requests',
                auth()->id()
            );
        }

        return response()->json([
            'req_number' => $result['req_number'],
            'created' => $result['created'],
            'material_id' => $result['material_id'],
            'material_name' => $result['material_name'],
            'shortage' => $result['shortage'],
            'unit' => $result['unit'],
            'notified' => $proUsers->count(),
        ]);
    }

    /**
     * Enforce the DSS gate. Returns an error string when the order must
     * be blocked (and files procurement suggestions), or null to proceed.
     */
    protected function enforceSustainability(SalesOrder $salesOrder, StockSustainabilityService $dss): ?string
    {
        $result = $dss->evaluate($salesOrder);

        if ($result['verdict'] === 'insufficient') {
            $suggested = $dss->suggestProcurement($salesOrder, $result, auth()->user()?->name);

            $lines = collect($result['materials'])
                ->filter(fn ($m) => ($m['shortage'] ?? 0) > 0)
                ->map(fn ($m) => "{$m['material_name']}: needs {$m['required']}{$m['unit']}, ATP {$m['atp']}{$m['unit']} (short {$m['shortage']}{$m['unit']})")
                ->implode('; ');

            $reqs = collect($suggested)->map(fn ($s) => $s['req_number'])->implode(', ');

            return "Order {$result['jo_number']} BLOCKED: stock cannot sustain this job after {$result['committed_orders']} accepted order(s). {$lines}. Procurement suggested: {$reqs} (see SCM → Procurement Orders).";
        }

        return null;
    }

    /**
     * Push to Supply Chain Management (SCM).
     *
     * The route passes {order} which is the sales_order primary-key (id).
     * We update its status so SCM can see it via ScmSalesOrderController::index().
     *
     * DSS gate: blocked when live stock minus already-committed orders
     * cannot cover this job; shortfalls are auto-suggested to procurement.
     */
    public function pushToScm($order, StockSustainabilityService $dss)
    {
        try {
            $salesOrder = SalesOrder::findOrFail($order);

            // Guard: only push if still pending
            if ($salesOrder->status !== 'pending') {
                return redirect()->back()->withErrors([
                    'error' => 'This order has already been pushed and cannot be pushed again.',
                ]);
            }

            if ($blocker = $this->enforceSustainability($salesOrder, $dss)) {
                return redirect()->back()->withErrors(['error' => $blocker]);
            }

            $data = request()->validate([
                'delivery_date' => 'nullable|date|after_or_equal:today',
            ]);

            $salesOrder->update([
                'status'    => 'pushed_to_scm',
                'pushed_to' => 'SCM',
                // Scheduled delivery chosen in the Push Center calendar UI.
                // Visible in tracking (Monti + client) as the committed date.
                'expected_ship_date' => $data['delivery_date'] ?? $salesOrder->expected_ship_date,
            ]);

            return redirect()->back()->with('success', 'Order successfully pushed to SCM.');
        } catch (\Exception $e) {
            Log::error('Push SCM Error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to push to SCM. Please try again.']);
        }
    }

    /**
     * Push to Order Management.
     *
     * Same DSS gate as SCM pushes — sustainability is checked before ANY
     * acceptance path.
     */
    public function pushToOrderMgmt($order, StockSustainabilityService $dss)
    {
        try {
            $salesOrder = SalesOrder::findOrFail($order);

            if ($salesOrder->status !== 'pending') {
                return redirect()->back()->withErrors([
                    'error' => 'This order has already been pushed and cannot be pushed again.',
                ]);
            }

            if ($blocker = $this->enforceSustainability($salesOrder, $dss)) {
                return redirect()->back()->withErrors(['error' => $blocker]);
            }

            $data = request()->validate([
                'delivery_date' => 'nullable|date|after_or_equal:today',
            ]);

            $salesOrder->update([
                'status'    => 'pushed_to_ordermgmt',
                'pushed_to' => 'Order Mgmt',
                'expected_ship_date' => $data['delivery_date'] ?? $salesOrder->expected_ship_date,
            ]);

            return redirect()->back()->with('success', 'Order forwarded to Order Management.');
        } catch (\Exception $e) {
            Log::error('Push Order Mgmt Error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to push to Order Management.']);
        }
    }

    /**
     * Manual Store (for staff overrides — uploads a PO attachment).
     */
    public function manualStore(Request $request)
    {
        $request->validate([
            'client_id'  => 'required|exists:clients,id',
            'attachment' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
        ]);

        $path = $request->file('attachment')->store('manual_pos', 'public');

        PurchaseOrder::create([
            'client_id'       => $request->client_id,
            'po_number'       => 'MPO-' . strtoupper(Str::random(8)),
            'status'          => 'approved',
            'subtotal'        => 0,
            'total_amount'    => 0,
            'attachment_path' => $path,
            'notes'           => 'Manually uploaded via ECO Push Center',
        ]);

        return redirect()->back()->with('success', 'Manual PO successfully uploaded and queued.');
    }

    /**
     * Manual Job Order store (staff override).
     * Creates a SalesOrder directly without going through the normal inquiry flow.
     */
    public function manualJobOrder(Request $request)
    {
        $request->validate([
            'client_id'    => 'required|exists:clients,id',
            'color'        => 'nullable|string|max:255',
            'yarn_type'    => 'nullable|string|max:255',
            'quantity'     => 'required|numeric|min:1',
            'total_amount' => 'required|numeric|min:0',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $joNumber = 'JO-' . strtoupper(Str::random(8));

        SalesOrder::create([
            'client_id'    => $request->client_id,
            'jo_number'    => $joNumber,
            'color'        => $request->color,
            'yarn_type'    => $request->yarn_type,
            'quantity'     => $request->quantity,
            'total_amount' => $request->total_amount,
            'status'       => 'pending',
            'notes'        => $request->notes ?? 'Manually created via ECO Push Center',
        ]);

        return redirect()->back()->with('success', "Manual Job Order {$joNumber} created successfully.");
    }
}