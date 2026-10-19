<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Core\AppSetting;
use App\Models\Logistics\Delivery;
use App\Models\Logistics\DeliveryStop;
use App\Models\Logistics\Driver;
use App\Models\Logistics\Truck;
use App\Models\Ceo\CeoLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class DriverController extends Controller
{
    /**
     * Get the authenticated driver (with fallback).
     */
    private function getDriver()
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        $driver = $user->driver;

        if (!$driver) {
            $driver = Driver::where('user_id', $user->id)->first();
            if ($driver) {
                $user->setRelation('driver', $driver);
            }
        }

        if (!$driver && $user->log_role === 'driver') {
            $driver = Driver::create([
                'user_id'        => $user->id,
                'license_number' => 'DRV-' . strtoupper(uniqid()),
                'is_available'   => true,
            ]);
            $user->setRelation('driver', $driver);
        }

        if (!$driver) {
            abort(403, 'You are not a registered driver.');
        }

        return $driver;
    }

    private function assertDeliveryOwned(Delivery $delivery, Driver $driver): void
    {
        if ((int) $delivery->driver_id !== (int) $driver->id) {
            abort(403, 'This delivery is not assigned to you.');
        }
    }

    private function assertOwnsDeliveryId(?int $deliveryId, Driver $driver): void
    {
        if ($deliveryId && ! $driver->deliveries()->whereKey($deliveryId)->exists()) {
            abort(403, 'This delivery is not assigned to you.');
        }
    }

    private function storeSignature(?string $dataUrl, string $folder): ?string
    {
        if (!$dataUrl || !preg_match('/^data:image\/(png|jpe?g);base64,(.+)$/', $dataUrl, $m)) {
            return null;
        }

        $binary = base64_decode($m[2], true);
        if ($binary === false) {
            return null;
        }

        $ext = in_array($m[1], ['jpg', 'jpeg'], true) ? 'jpg' : 'png';
        $path = $folder . '/sig_' . uniqid() . '.' . $ext;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function minutesFor($log): int
    {
        if ($log->duration_minutes !== null) {
            return (int) $log->duration_minutes;
        }

        $end = $log->ended_at ?? now();

        return max(0, (int) $log->started_at->diffInMinutes($end));
    }

    public function index()
    {
        $user = Auth::user();
        $driver = $this->getDriver();

        $activeDeliveries = Delivery::where('driver_id', $driver->id)
            ->whereIn('status', ['dispatched', 'in_transit'])
            ->with(['packages', 'route', 'stops.client', 'truck', 'conductor1.user', 'conductor2.user'])
            ->get();

        $history = Delivery::where('driver_id', $driver->id)
            ->whereIn('status', ['delivered', 'cancelled'])
            ->with(['route', 'stops', 'truck'])
            ->latest('updated_at')
            ->limit(40)
            ->get();

        $dutyLogs = $driver->dutyLogs()->limit(30)->get();
        $currentDuty = $driver->dutyLogs()->whereNull('ended_at')->latest('started_at')->first();

        $todayLogs = $driver->dutyLogs()->whereDate('started_at', today())->get();
        $todayMinutes = [
            'on_duty' => $todayLogs->where('status', 'on_duty')->sum(fn ($l) => $this->minutesFor($l)),
            'driving' => $todayLogs->where('status', 'driving')->sum(fn ($l) => $this->minutesFor($l)),
            'on_break' => $todayLogs->where('status', 'on_break')->sum(fn ($l) => $this->minutesFor($l)),
            'off_duty' => $todayLogs->where('status', 'off_duty')->sum(fn ($l) => $this->minutesFor($l)),
        ];

        $inspections = $driver->inspections()->with('truck')->limit(15)->get();
        $incidents = $driver->incidents()->with(['delivery', 'truck'])->limit(15)->get();
        $expenses = $driver->expenses()->with('delivery')->limit(30)->get();

        $expenseTotals = [
            'pending' => (float) $driver->expenses()->where('status', 'pending')->sum('amount'),
            'approved' => (float) $driver->expenses()->where('status', 'approved')->sum('amount'),
        ];

        $documents = $driver->documents()->get()->map(fn ($doc) => array_merge(
            $doc->toArray(),
            [
                'is_expired' => $doc->is_expired,
                'expiring_soon' => $doc->expiring_soon,
            ]
        ));

        $completed = Delivery::where('driver_id', $driver->id)
            ->where('status', 'delivered')
            ->with('stops')
            ->latest('arrival_time')
            ->limit(60)
            ->get();

        $earningsRows = $completed->map(function (Delivery $delivery) {
            $km = (float) ($delivery->total_distance_km ?? 0);
            $stops = $delivery->stops->count();
            $base = 800.0;
            $mileage = round($km * 15, 2);
            $stopBonus = $stops * 50.0;
            $ratingBonus = ((int) ($delivery->rating ?? 0)) >= 4 ? 200.0 : 0.0;

            return [
                'id' => $delivery->id,
                'delivery_number' => $delivery->delivery_number,
                'completed_at' => $delivery->arrival_time ?? $delivery->updated_at,
                'distance_km' => $km,
                'stops' => $stops,
                'rating' => $delivery->rating,
                'base' => $base,
                'mileage' => $mileage,
                'stop_bonus' => $stopBonus,
                'rating_bonus' => $ratingBonus,
                'total' => $base + $mileage + $stopBonus + $ratingBonus,
                'status' => $delivery->updated_at && $delivery->updated_at->lt(now()->subDays(7)) ? 'settled' : 'pending',
            ];
        });

        $earnings = [
            'rows' => $earningsRows,
            'month_total' => $earningsRows->filter(
                fn ($r) => $r['completed_at'] && $r['completed_at']->isSameMonth(now())
            )->sum('total'),
            'pending_total' => $earningsRows->where('status', 'pending')->sum('total'),
            'lifetime_total' => $earningsRows->sum('total'),
        ];

        $monthDeliveries = Delivery::where('driver_id', $driver->id)
            ->where('status', 'delivered')
            ->whereMonth('arrival_time', now()->month)
            ->whereYear('arrival_time', now()->year);

        $stats = [
            'active' => $activeDeliveries->count(),
            'completed_month' => (clone $monthDeliveries)->count(),
            'km_month' => (float) (clone $monthDeliveries)->sum('total_distance_km'),
            'rating' => (float) ($driver->rating ?? 0),
            'on_duty_minutes_today' => $todayMinutes['on_duty'] + $todayMinutes['driving'],
            'driving_minutes_today' => $todayMinutes['driving'],
        ];

        $currentTruck = $activeDeliveries->first()?->truck;

        return inertia('Dashboard/Logistics/DriverPortal', [
            'deliveries'     => $activeDeliveries,
            'history'        => $history,
            'driver'         => $driver->load('user'),
            'user'           => $user->only('id', 'name', 'email', 'profile_photo_path'),
            'montiLocation'  => CeoLocation::latest()->first(),
            'montiLocations' => CeoLocation::where('is_active', true)->latest()->get(),
            'trucks'         => Truck::orderBy('truck_number')->get(['id', 'truck_number', 'plate_number', 'model', 'status', 'mileage']),
            'currentTruck'   => $currentTruck,
            'duty'           => [
                'current' => $currentDuty,
                'logs' => $dutyLogs,
                'today_minutes' => $todayMinutes,
            ],
            'inspections'    => $inspections,
            'incidents'      => $incidents,
            'expenses'       => $expenses,
            'expense_totals' => $expenseTotals,
            'documents'      => $documents,
            'earnings'       => $earnings,
            'stats'          => $stats,
            'deliverySimulation' => AppSetting::getBool(AppSetting::LOGISTICS_DELIVERY_SIMULATION),
        ]);
    }

    public function updateDutyStatus(Request $request)
    {
        $driver = $this->getDriver();

        $validated = $request->validate([
            'status'        => 'required|in:off_duty,on_duty,driving,on_break',
            'latitude'      => 'nullable|numeric',
            'longitude'     => 'nullable|numeric',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $current = $driver->dutyLogs()->whereNull('ended_at')->latest('started_at')->first();
        if ($current) {
            $current->update([
                'ended_at' => now(),
                'duration_minutes' => $this->minutesFor($current),
            ]);
        }

        $driver->dutyLogs()->create([
            'status' => $validated['status'],
            'started_at' => now(),
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Duty status updated.');
    }

    public function endDutyLog(Request $request)
    {
        $driver = $this->getDriver();

        $validated = $request->validate([
            'signature' => 'nullable|string',
            'notes'     => 'nullable|string|max:2000',
        ]);

        $current = $driver->dutyLogs()->whereNull('ended_at')->latest('started_at')->first();
        if (!$current) {
            return back()->with('error', 'No open duty log to sign off.');
        }

        $current->update([
            'ended_at' => now(),
            'duration_minutes' => $this->minutesFor($current),
            'notes' => $validated['notes'] ?? $current->notes,
            'signature_path' => $this->storeSignature($validated['signature'] ?? null, 'driver/signatures')
                ?? $current->signature_path,
        ]);

        return back()->with('success', 'Daily duty log signed off.');
    }

    public function storeInspection(Request $request)
    {
        $driver = $this->getDriver();

        $validated = $request->validate([
            'type'        => 'required|in:pre_trip,post_trip',
            'truck_id'    => 'nullable|exists:trucks,id',
            'delivery_id' => 'nullable|exists:deliveries,id',
            'checklist'   => 'nullable|array',
            'defects'     => 'nullable|string|max:2000',
            'severity'    => 'required|in:none,minor,critical',
            'photo'       => 'nullable|image|max:5120',
        ]);

        $this->assertOwnsDeliveryId($validated['delivery_id'] ?? null, $driver);

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('driver/inspections', 'public')
            : null;

        $driver->inspections()->create([
            'truck_id' => $validated['truck_id'] ?? null,
            'delivery_id' => $validated['delivery_id'] ?? null,
            'type' => $validated['type'],
            'checklist' => $validated['checklist'] ?? [],
            'defects' => $validated['defects'] ?? null,
            'severity' => $validated['severity'],
            'photo_path' => $photoPath,
            'passed' => $validated['severity'] !== 'critical',
            'inspected_at' => now(),
        ]);

        return back()->with('success', 'Vehicle inspection submitted.');
    }

    public function storeIncident(Request $request)
    {
        $driver = $this->getDriver();

        $validated = $request->validate([
            'type'              => 'required|in:delay,accident,breakdown,other',
            'category'          => 'nullable|string|max:120',
            'severity'          => 'required|in:low,medium,high,critical',
            'description'       => 'required|string|max:5000',
            'location_lat'      => 'nullable|numeric',
            'location_lng'      => 'nullable|numeric',
            'location_text'     => 'nullable|string|max:255',
            'third_party_info'  => 'nullable|string|max:2000',
            'delivery_id'       => 'nullable|exists:deliveries,id',
            'truck_id'          => 'nullable|exists:trucks,id',
            'photo'             => 'nullable|image|max:5120',
        ]);

        $this->assertOwnsDeliveryId($validated['delivery_id'] ?? null, $driver);

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('driver/incidents', 'public')
            : null;

        $driver->incidents()->create([
            'delivery_id' => $validated['delivery_id'] ?? null,
            'truck_id' => $validated['truck_id'] ?? null,
            'type' => $validated['type'],
            'category' => $validated['category'] ?? null,
            'severity' => $validated['severity'],
            'description' => $validated['description'],
            'location_lat' => $validated['location_lat'] ?? null,
            'location_lng' => $validated['location_lng'] ?? null,
            'location_text' => $validated['location_text'] ?? null,
            'third_party_info' => $validated['third_party_info'] ?? null,
            'photo_path' => $photoPath,
            'status' => 'open',
            'reported_at' => now(),
        ]);

        return back()->with('success', 'Report submitted. Dispatch has been notified.');
    }

    public function storeExpense(Request $request)
    {
        $driver = $this->getDriver();

        $validated = $request->validate([
            'type'        => 'required|in:fuel,toll,scale,repair,other',
            'amount'      => 'required|numeric|min:0',
            'delivery_id' => 'nullable|exists:deliveries,id',
            'notes'       => 'nullable|string|max:2000',
            'spent_at'    => 'required|date',
            'receipt'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $this->assertOwnsDeliveryId($validated['delivery_id'] ?? null, $driver);

        $receiptPath = $request->hasFile('receipt')
            ? $request->file('receipt')->store('driver/receipts', 'public')
            : null;

        $driver->expenses()->create([
            'delivery_id' => $validated['delivery_id'] ?? null,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'receipt_path' => $receiptPath,
            'notes' => $validated['notes'] ?? null,
            'spent_at' => $validated['spent_at'],
            'status' => 'pending',
        ]);

        return back()->with('success', 'Expense submitted for reimbursement.');
    }

    public function storeDocument(Request $request)
    {
        $driver = $this->getDriver();

        $validated = $request->validate([
            'document_type' => 'required|in:license,medical,hazmat,vehicle_registration,vehicle_insurance,vehicle_permit,other',
            'title'         => 'required|string|max:255',
            'number'        => 'nullable|string|max:255',
            'issued_at'     => 'nullable|date',
            'expires_at'    => 'nullable|date|after_or_equal:issued_at',
            'notes'         => 'nullable|string|max:2000',
            'file'          => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $filePath = $request->hasFile('file')
            ? $request->file('file')->store('driver/documents', 'public')
            : null;

        $driver->documents()->create([
            'document_type' => $validated['document_type'],
            'title' => $validated['title'],
            'number' => $validated['number'] ?? null,
            'issued_at' => $validated['issued_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'file_path' => $filePath,
        ]);

        return back()->with('success', 'Document saved.');
    }

    public function destroyDocument($document)
    {
        $authDriver = $this->getDriver();

        $doc = $authDriver->documents()->findOrFail($document);

        if ($doc->file_path) {
            Storage::disk('public')->delete($doc->file_path);
        }

        $doc->delete();

        return back()->with('success', 'Document deleted.');
    }

    public function arriveStop(Delivery $delivery, DeliveryStop $stop)
    {
        $driver = $this->getDriver();
        $this->assertDeliveryOwned($delivery, $driver);

        if ((int) $stop->delivery_id !== (int) $delivery->id) {
            abort(404);
        }

        $stop->update(['status' => 'arrived', 'arrived_at' => now()]);

        return back()->with('success', 'Arrival recorded.');
    }

    public function completeStop(Request $request, Delivery $delivery, DeliveryStop $stop)
    {
        $driver = $this->getDriver();
        $this->assertDeliveryOwned($delivery, $driver);

        if ((int) $stop->delivery_id !== (int) $delivery->id) {
            abort(404);
        }

        $validated = $request->validate([
            'outcome'        => 'required|in:delivered,attempted,refused,partial',
            'recipient_name' => 'nullable|string|max:255',
            'pod_notes'      => 'nullable|string|max:2000',
            'signature'      => 'nullable|string',
            'photo'          => 'nullable|image|max:5120',
        ]);

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('driver/pod', 'public')
            : null;

        $signaturePath = $this->storeSignature($validated['signature'] ?? null, 'driver/pod');

        $stop->update([
            'outcome' => $validated['outcome'],
            'status' => in_array($validated['outcome'], ['delivered', 'partial'], true) ? 'delivered' : 'arrived',
            'recipient_name' => $validated['recipient_name'] ?? $stop->recipient_name,
            'pod_notes' => $validated['pod_notes'] ?? $stop->pod_notes,
            'signature_path' => $signaturePath ?? $stop->signature_path,
            'pod_photo_path' => $photoPath ?? $stop->pod_photo_path,
            'completed_at' => now(),
        ]);

        if (in_array($validated['outcome'], ['delivered', 'partial'], true)) {
            $packageIds = $delivery->packages()
                ->wherePivot('delivery_stop_id', $stop->id)
                ->pluck('warehouse_packages.id');

            if ($packageIds->isNotEmpty()) {
                \App\Models\War\WarehousePackage::whereIn('id', $packageIds)
                    ->update(['status' => 'delivered']);
            }
        }

        $delivery->load('stops');
        $allHandled = $delivery->stops->every(fn ($s) => $s->outcome !== 'pending');

        if ($allHandled && $delivery->status !== 'delivered') {
            $delivery->update(['status' => 'delivered', 'arrival_time' => now()]);

            if ($delivery->truck) {
                $delivery->truck->update(['status' => 'available']);
            }
            if ($delivery->driver) {
                $delivery->driver->update(['is_available' => true]);
            }
            if ($delivery->conductor1) {
                $delivery->conductor1->update(['is_available' => true]);
            }
            if ($delivery->conductor2) {
                $delivery->conductor2->update(['is_available' => true]);
            }
        }

        return back()->with('success', 'Stop updated: ' . $validated['outcome'] . '.');
    }

    public function markInTransit(Delivery $delivery)
    {
        $driver = $this->getDriver();
        $this->assertDeliveryOwned($delivery, $driver);

        $delivery->update([
            'status'           => 'in_transit',
            'actual_departure' => now(),
        ]);

        return back()->with('success', 'Trip started.');
    }

    public function uploadProof(Request $request, Delivery $delivery)
    {
        $driver = $this->getDriver();
        $this->assertDeliveryOwned($delivery, $driver);

        $request->validate([
            'image' => 'required|image|max:5120',
            'notes' => 'nullable|string',
        ]);

        $path = $request->file('image')->store('proof_of_delivery', 'public');

        \App\Models\Logistics\ProofOfDelivery::create([
            'delivery_id' => $delivery->id,
            'image_path'  => $path,
            'notes'       => $request->notes,
            'delivered_at'=> now(),
        ]);

        $delivery->update(['status' => 'delivered', 'arrival_time' => now()]);

        foreach ($delivery->packages as $package) {
            $package->update(['status' => 'delivered']);
        }

        if ($delivery->truck) $delivery->truck->update(['status' => 'available']);
        if ($delivery->driver) $delivery->driver->update(['is_available' => true]);
        if ($delivery->conductor1) $delivery->conductor1->update(['is_available' => true]);
        if ($delivery->conductor2) $delivery->conductor2->update(['is_available' => true]);

        return back()->with('success', 'Delivery completed and proof uploaded.');
    }

    public function testDelivered(Delivery $delivery)
    {
        $driver = $this->getDriver();
        $this->assertDeliveryOwned($delivery, $driver);

        $delivery->update(['status' => 'delivered', 'arrival_time' => now()]);

        foreach ($delivery->packages as $package) {
            $package->update(['status' => 'delivered']);
        }

        if ($delivery->truck) $delivery->truck->update(['status' => 'available']);
        if ($delivery->driver) $delivery->driver->update(['is_available' => true]);
        if ($delivery->conductor1) $delivery->conductor1->update(['is_available' => true]);
        if ($delivery->conductor2) $delivery->conductor2->update(['is_available' => true]);

        return back()->with('success', 'Delivery marked as delivered (test mode).');
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email,' . $user->id,
            'password'      => ['nullable', 'confirmed', Password::defaults()],
            'profile_photo' => 'nullable|image|max:2048',
        ]);

        $user->name  = $validated['name'];
        $user->email = $validated['email'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $user->profile_photo_path = $path;
        }

        $user->save();

        return back()->with('success', 'Profile updated successfully.');
    }
}
