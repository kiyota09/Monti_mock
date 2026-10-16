<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\Ceo\CeoLocation;
use Exception;

class GeolocationController extends Controller
{
    /**
     * Display the Geolocation Hub with ALL company sites.
     */
    public function index()
    {
        $userId = auth()->id();

        // Every pin ever saved by this user — the site list.
        // Active ones are the valid company locations (HQ, warehouses, branches).
        $sites = CeoLocation::where('user_id', $userId)
            ->orderByDesc('is_active')
            ->latest()
            ->take(100)
            ->get();

        $activeSites = $sites->where('is_active', true)->values();

        // Backward compat for the map's initial center + old consumers.
        $lastLocation = $sites->first();

        return Inertia::render('Dashboard/IT/Geolocation', [
            'savedLocation' => $lastLocation,
            'locationHistory' => $sites,
            'sites' => $sites,
            'activeSites' => $activeSites,
        ]);
    }

    /**
     * Store a new company site (warehouse, branch, HQ, ...).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'range_radius' => 'required|integer|min:1|max:100000',
            'label' => 'required|string|max:255',
            'place_name' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $location = CeoLocation::create([
                'user_id' => auth()->id(),
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'range_radius' => $validated['range_radius'],
                'label' => $validated['label'],
                'place_name' => $validated['place_name'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            Log::info("IT Geolocation Site Added", [
                'user_id' => auth()->id(),
                'location_id' => $location->id,
                'label' => $location->label,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "'{$location->label}' successfully saved.",
                'data' => $location
            ]);

        } catch (Exception $e) {
            Log::error("Failed to archive IT Geolocation: " . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Database failure. Please verify migrations.',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update a site's name / coordinates / radius.
     */
    public function update(Request $request, CeoLocation $location)
    {
        $this->authorizeSite($location);

        $validated = $request->validate([
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'range_radius' => 'sometimes|integer|min:1|max:100000',
            'label' => 'sometimes|string|max:255',
            'place_name' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        $location->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => "'{$location->label}' updated.",
            'data' => $location->fresh(),
        ]);
    }

    /**
     * Activate / deactivate a site without deleting it.
     */
    public function toggle(CeoLocation $location)
    {
        $this->authorizeSite($location);

        $location->update(['is_active' => ! $location->is_active]);

        return response()->json([
            'status' => 'success',
            'message' => $location->is_active
                ? "'{$location->label}' is now an active work location."
                : "'{$location->label}' deactivated.",
            'data' => $location->fresh(),
        ]);
    }

    /**
     * Permanently remove a site.
     */
    public function destroy(CeoLocation $location)
    {
        $this->authorizeSite($location);

        $label = $location->label;
        $location->delete();

        return response()->json([
            'status' => 'success',
            'message' => "'{$label}' removed.",
        ]);
    }

    private function authorizeSite(CeoLocation $location): void
    {
        // Users only manage their own pins; CEOs/admins via IT manager role
        // keep global access through the page.permission middleware.
        if ((int) $location->user_id !== (int) auth()->id()) {
            abort(403, 'You can only manage your own locations.');
        }
    }
}
