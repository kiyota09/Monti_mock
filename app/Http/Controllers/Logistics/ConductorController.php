<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Logistics\Delivery;
use App\Models\Logistics\Conductor;
use App\Models\Logistics\ConductorReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConductorController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $conductor = $user->conductor;

        // If relationship not loaded, try direct query
        if (!$conductor) {
            $conductor = Conductor::where('user_id', $user->id)->first();
            if ($conductor) {
                $user->setRelation('conductor', $conductor);
            }
        }

        // Auto-create conductor record if user is a LOG staff (or log_role = conductor)
        if (!$conductor && ($user->log_role === 'conductor' || $user->role === 'LOG')) {
            $conductor = Conductor::create([
                'user_id' => $user->id,
                'is_available' => true,
            ]);
            $user->setRelation('conductor', $conductor);
            // Ensure log_role is set
            if ($user->log_role !== 'conductor') {
                $user->log_role = 'conductor';
                $user->save();
            }
        }

        if (!$conductor) {
            abort(403, 'You are not a registered conductor.');
        }

        $deliveries = Delivery::where(function ($q) use ($conductor) {
            $q->where('conductor1_id', $conductor->id)
              ->orWhere('conductor2_id', $conductor->id);
        })->whereIn('status', ['dispatched', 'in_transit', 'delivered'])
          ->with(['driver.user', 'route', 'packages'])
          ->get();

        return inertia('Dashboard/Logistics/ConductorPortal', ['deliveries' => $deliveries]);
    }

    public function storeReport(Request $request, Delivery $delivery)
    {
        $user = Auth::user();
        $conductor = $user->conductor;

        // Ensure conductor exists (auto-create if needed)
        if (!$conductor) {
            $conductor = Conductor::where('user_id', $user->id)->first();
            if (!$conductor && $user->log_role === 'conductor') {
                $conductor = Conductor::create([
                    'user_id' => $user->id,
                    'is_available' => true,
                ]);
                $user->setRelation('conductor', $conductor);
            }
        }

        if (!$conductor || ($delivery->conductor1_id !== $conductor->id && $delivery->conductor2_id !== $conductor->id)) {
            abort(403);
        }

        $request->validate(['report' => 'required|string']);

        ConductorReport::create([
            'delivery_id' => $delivery->id,
            'conductor_id' => $conductor->id,
            'report_text' => $request->report,
        ]);

        return back()->with('success', 'Report submitted.');
    }
}