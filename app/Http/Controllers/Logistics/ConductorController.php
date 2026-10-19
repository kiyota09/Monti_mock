<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Ceo\CeoLocation;
use App\Models\Logistics\Conductor;
use App\Models\Logistics\ConductorCollection;
use App\Models\Logistics\Delivery;
use App\Models\Logistics\DeliveryStop;
use App\Models\Logistics\ConductorReturn;
use App\Models\Logistics\ConductorReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ConductorController extends Controller
{
    /**
     * Resolve the authenticated conductor (auto-provision only real conductors).
     */
    private function getConductor(): Conductor
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        $conductor = $user->conductor;

        if (!$conductor) {
            $conductor = Conductor::where('user_id', $user->id)->first();
            if ($conductor) {
                $user->setRelation('conductor', $conductor);
            }
        }

        if (!$conductor && $user->log_role === 'conductor') {
            $conductor = Conductor::create([
                'user_id' => $user->id,
                'is_available' => true,
            ]);
            $user->setRelation('conductor', $conductor);
        }

        if (!$conductor) {
            abort(403, 'You are not a registered conductor.');
        }

        return $conductor;
    }

    private function assertOnDelivery(Delivery $delivery, Conductor $conductor): void
    {
        if ((int) $delivery->conductor1_id !== (int) $conductor->id
            && (int) $delivery->conductor2_id !== (int) $conductor->id) {
            abort(403, 'This delivery is not assigned to you.');
        }
    }

    private function assertStopBelongs(?int $stopId, Delivery $delivery): ?DeliveryStop
    {
        if (!$stopId) {
            return null;
        }

        $stop = DeliveryStop::findOrFail($stopId);

        if ((int) $stop->delivery_id !== (int) $delivery->id) {
            abort(404);
        }

        return $stop;
    }

    public function index()
    {
        $user = Auth::user();
        $conductor = $this->getConductor();

        $assigned = function ($query) use ($conductor) {
            $query->where('conductor1_id', $conductor->id)
                ->orWhere('conductor2_id', $conductor->id);
        };

        $deliveries = Delivery::where($assigned)
            ->whereIn('status', ['dispatched', 'in_transit'])
            ->with([
                'driver.user',
                'route',
                'truck',
                'packages.product',
                'stops.client',
            ])
            ->latest('scheduled_departure')
            ->get();

        $history = Delivery::where($assigned)
            ->whereIn('status', ['delivered', 'cancelled'])
            ->with(['driver.user', 'route', 'truck', 'packages.product', 'stops.client'])
            ->latest('updated_at')
            ->limit(40)
            ->get();

        $loadChecks = $conductor->loadChecks()
            ->with('delivery:id,delivery_number,status')
            ->limit(30)
            ->get();

        $collections = $conductor->collections()
            ->with(['delivery:id,delivery_number', 'client:id,company_name', 'stop:id,label'])
            ->limit(50)
            ->get();

        $returns = $conductor->returns()
            ->with(['delivery:id,delivery_number', 'client:id,company_name', 'stop:id,label'])
            ->limit(50)
            ->get();

        $reports = $conductor->reports()
            ->with('delivery:id,delivery_number')
            ->limit(30)
            ->get();

        $collectionTotals = [
            'today' => (float) $conductor->collections()->whereDate('collected_at', today())->sum('amount'),
            'pending' => (float) $conductor->collections()->where('status', 'pending')->sum('amount'),
            'remitted' => (float) $conductor->collections()->whereIn('status', ['remitted', 'verified'])->sum('amount'),
            'lifetime' => (float) $conductor->collections()->where('status', '!=', 'void')->sum('amount'),
        ];

        $returnTotals = [
            'open' => (int) $conductor->returns()->where('status', 'open')->count(),
            'resolved' => (int) $conductor->returns()->where('status', 'resolved')->count(),
            'open_quantity' => (int) $conductor->returns()->where('status', 'open')->sum('quantity'),
        ];

        $pendingStops = $deliveries->sum(function ($delivery) {
            return $delivery->stops->filter(fn ($stop) => ($stop->outcome ?: 'pending') === 'pending')->count();
        });

        $packagesOnBoard = $deliveries->sum(fn ($delivery) => $delivery->packages->count());

        $completedThisMonth = Delivery::where($assigned)
            ->where('status', 'delivered')
            ->whereMonth('arrival_time', now()->month)
            ->whereYear('arrival_time', now()->year)
            ->count();

        $stats = [
            'active_trips' => $deliveries->count(),
            'pending_stops' => $pendingStops,
            'packages_on_board' => $packagesOnBoard,
            'collected_today' => $collectionTotals['today'],
            'pending_remittance' => $collectionTotals['pending'],
            'open_returns' => $returnTotals['open'],
            'open_reports' => (int) $conductor->reports()->where('status', 'open')->count(),
            'completed_month' => $completedThisMonth,
        ];

        return inertia('Dashboard/Logistics/ConductorPortal', [
            'conductor' => $conductor->load('user'),
            'user' => $user->only('id', 'name', 'email', 'profile_photo_path'),
            'deliveries' => $deliveries,
            'history' => $history,
            'loadChecks' => $loadChecks,
            'collections' => $collections,
            'collectionTotals' => $collectionTotals,
            'returns' => $returns,
            'returnTotals' => $returnTotals,
            'reports' => $reports,
            'stats' => $stats,
            'currentDelivery' => $deliveries->first(),
            'montiLocation' => CeoLocation::latest()->first(),
        ]);
    }

    public function storeLoadCheck(Request $request)
    {
        $conductor = $this->getConductor();

        $validated = $request->validate([
            'delivery_id' => 'required|exists:deliveries,id',
            'type' => 'required|in:pre_trip,post_trip',
            'checklist' => 'nullable|array',
            'discrepancies' => 'nullable|string|max:2000',
            'severity' => 'required|in:none,minor,critical',
            'photo' => 'nullable|image|max:5120',
        ]);

        $delivery = Delivery::findOrFail($validated['delivery_id']);
        $this->assertOnDelivery($delivery, $conductor);

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('conductor/load-checks', 'public')
            : null;

        $conductor->loadChecks()->create([
            'delivery_id' => $delivery->id,
            'type' => $validated['type'],
            'checklist' => $validated['checklist'] ?? [],
            'discrepancies' => $validated['discrepancies'] ?? null,
            'severity' => $validated['severity'],
            'photo_path' => $photoPath,
            'passed' => $validated['severity'] !== 'critical',
            'checked_at' => now(),
        ]);

        return back()->with('success', 'Load check recorded.');
    }

    public function storeCollection(Request $request)
    {
        $conductor = $this->getConductor();

        $validated = $request->validate([
            'delivery_id' => 'required|exists:deliveries,id',
            'delivery_stop_id' => 'nullable|exists:delivery_stops,id',
            'client_id' => 'nullable|exists:clients,id',
            'method' => 'required|in:cash,cheque,bank_transfer,e_wallet,credit',
            'reference' => 'nullable|string|max:120',
            'amount' => 'required|numeric|min:0.01',
            'remarks' => 'nullable|string|max:2000',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $delivery = Delivery::findOrFail($validated['delivery_id']);
        $this->assertOnDelivery($delivery, $conductor);

        $stop = $this->assertStopBelongs($validated['delivery_stop_id'] ?? null, $delivery);

        $receiptPath = $request->hasFile('receipt')
            ? $request->file('receipt')->store('conductor/collections', 'public')
            : null;

        $conductor->collections()->create([
            'delivery_id' => $delivery->id,
            'delivery_stop_id' => $stop?->id,
            'client_id' => $validated['client_id'] ?? $stop?->client_id,
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'amount' => $validated['amount'],
            'receipt_path' => $receiptPath,
            'remarks' => $validated['remarks'] ?? null,
            'status' => 'pending',
            'collected_at' => now(),
        ]);

        return back()->with('success', 'Collection recorded. Remember to remit it to treasury.');
    }

    public function remitCollection(ConductorCollection $collection)
    {
        $conductor = $this->getConductor();

        if ((int) $collection->conductor_id !== (int) $conductor->id) {
            abort(403);
        }

        if ($collection->status === 'void') {
            return back()->with('error', 'This collection was voided and cannot be remitted.');
        }

        $collection->update([
            'status' => 'remitted',
            'remitted_at' => now(),
        ]);

        return back()->with('success', 'Collection marked as remitted to treasury.');
    }

    public function storeReturn(Request $request)
    {
        $conductor = $this->getConductor();

        $validated = $request->validate([
            'delivery_id' => 'required|exists:deliveries,id',
            'delivery_stop_id' => 'nullable|exists:delivery_stops,id',
            'client_id' => 'nullable|exists:clients,id',
            'package_number' => 'nullable|string|max:120',
            'product_name' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|in:damaged,wrong_item,shortage,overage,refused,expired,other',
            'action' => 'required|in:return_to_warehouse,replace,credit,dispose',
            'notes' => 'nullable|string|max:2000',
            'photo' => 'nullable|image|max:5120',
        ]);

        $delivery = Delivery::findOrFail($validated['delivery_id']);
        $this->assertOnDelivery($delivery, $conductor);

        $stop = $this->assertStopBelongs($validated['delivery_stop_id'] ?? null, $delivery);

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('conductor/returns', 'public')
            : null;

        $conductor->returns()->create([
            'delivery_id' => $delivery->id,
            'delivery_stop_id' => $stop?->id,
            'client_id' => $validated['client_id'] ?? $stop?->client_id,
            'package_number' => $validated['package_number'] ?? null,
            'product_name' => $validated['product_name'] ?? null,
            'quantity' => $validated['quantity'],
            'reason' => $validated['reason'],
            'action' => $validated['action'],
            'photo_path' => $photoPath,
            'notes' => $validated['notes'] ?? null,
            'status' => 'open',
            'reported_at' => now(),
        ]);

        return back()->with('success', 'Return / damage logged for warehouse follow-up.');
    }

    public function resolveReturn(ConductorReturn $return)
    {
        $conductor = $this->getConductor();

        if ((int) $return->conductor_id !== (int) $conductor->id) {
            abort(403);
        }

        $return->update(['status' => 'resolved']);

        return back()->with('success', 'Return marked as resolved.');
    }

    public function storeReport(Request $request, Delivery $delivery)
    {
        $conductor = $this->getConductor();
        $this->assertOnDelivery($delivery, $conductor);

        $validated = $request->validate([
            'report_text' => 'required|string|max:5000',
            'category' => 'nullable|in:delay,accident,breakdown,customer,safety,other',
            'severity' => 'nullable|in:low,medium,high,critical',
            'photo' => 'nullable|image|max:5120',
        ]);

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('conductor/reports', 'public')
            : null;

        ConductorReport::create([
            'delivery_id' => $delivery->id,
            'conductor_id' => $conductor->id,
            'report_text' => $validated['report_text'],
            'category' => $validated['category'] ?? 'other',
            'severity' => $validated['severity'] ?? 'medium',
            'photo_path' => $photoPath,
            'status' => 'open',
            'reported_at' => now(),
        ]);

        return back()->with('success', 'Report submitted to the Logistics Manager.');
    }
}
