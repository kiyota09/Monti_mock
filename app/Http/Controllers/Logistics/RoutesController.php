<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Logistics\Route;
use App\Models\Crm\Client;
use App\Models\Ceo\CeoLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RoutesController extends Controller
{
    public function index()
    {
        $routes = Route::with('client')->latest()->get();

        $clients = Client::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->select('id', 'company_name', 'company_address', 'city', 'province', 'latitude', 'longitude')
            ->orderBy('company_name')
            ->get();

        $montiLocation = CeoLocation::latest()->first();

        return inertia('Dashboard/Logistics/Routes', [
            'routes'        => $routes,
            'clients'       => $clients,
            'montiLocation' => $montiLocation,
            'montiLocations' => CeoLocation::where('is_active', true)->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'client_id'        => 'nullable|exists:clients,id',
            'origin'           => 'required|string|max:255',
            'origin_lat'       => 'nullable|numeric',
            'origin_lng'       => 'nullable|numeric',
            'destination'      => 'required|string|max:255',
            'destination_lat'  => 'nullable|numeric',
            'destination_lng'  => 'nullable|numeric',
            'distance_km'      => 'required|numeric|min:0',
            'estimated_minutes'=> 'required|integer|min:1',
            'route_geometry'   => 'nullable|array',
        ]);

        $validated = $this->resolveGeometry($validated);

        Route::create($validated);
        return back()->with('success', 'Route added.');
    }

    public function update(Request $request, Route $route)
    {
        $validated = $request->validate([
            'name'             => 'sometimes|string|max:255',
            'client_id'        => 'nullable|exists:clients,id',
            'origin'           => 'sometimes|string|max:255',
            'origin_lat'       => 'nullable|numeric',
            'origin_lng'       => 'nullable|numeric',
            'destination'      => 'sometimes|string|max:255',
            'destination_lat'  => 'nullable|numeric',
            'destination_lng'  => 'nullable|numeric',
            'distance_km'      => 'sometimes|numeric|min:0',
            'estimated_minutes'=> 'sometimes|integer|min:1',
            'route_geometry'   => 'nullable|array',
        ]);

        $validated = $this->resolveGeometry($validated);

        $route->update($validated);
        return back()->with('success', 'Route updated.');
    }

    public function destroy(Route $route)
    {
        $route->delete();
        return back()->with('success', 'Route deleted.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Always attempt server-side OSRM when origin/destination coords exist.
    // Server-to-server has no CORS restriction, so this is far more reliable
    // than letting the browser call OSRM directly.
    // ─────────────────────────────────────────────────────────────────────
    private function resolveGeometry(array $data): array
    {
        $hasCoords = isset(
            $data['origin_lat'], $data['origin_lng'],
            $data['destination_lat'], $data['destination_lng']
        );

        if (! $hasCoords) {
            // No coordinates — JSON-encode any frontend geometry and return
            if (is_array($data['route_geometry'] ?? null)) {
                $data['route_geometry'] = json_encode($data['route_geometry']);
            }
            return $data;
        }

        $osrm = $this->fetchOsrmRoute(
            (float) $data['origin_lat'],
            (float) $data['origin_lng'],
            (float) $data['destination_lat'],
            (float) $data['destination_lng']
        );

        if ($osrm) {
            // OSRM succeeded server-side — override with accurate road data
            $data['route_geometry']    = json_encode($osrm['geometry']);
            $data['distance_km']       = $osrm['distance_km'];
            $data['estimated_minutes'] = $osrm['estimated_minutes'];
        } elseif (is_array($data['route_geometry'] ?? null)) {
            // OSRM unavailable but browser managed to fetch geometry — keep it
            $data['route_geometry'] = json_encode($data['route_geometry']);
        } else {
            // No geometry available from either source — straight-line fallback
            $data['route_geometry'] = null;
        }

        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Call OSRM from the server. Returns real road geometry + distance/time,
    // or null if unreachable.
    // ─────────────────────────────────────────────────────────────────────
    private function fetchOsrmRoute(
        float $oLat, float $oLng,
        float $dLat, float $dLng
    ): ?array {
        try {
            // OSRM expects coordinates as lng,lat
            $url = sprintf(
                'https://router.project-osrm.org/route/v1/driving/%s,%s;%s,%s?overview=full&geometries=geojson',
                $oLng, $oLat, $dLng, $dLat
            );

            $response = Http::timeout(15)->get($url);

            if (! $response->ok()) {
                return null;
            }

            $payload = $response->json();

            if (empty($payload['routes'][0]['geometry']['coordinates'])) {
                return null;
            }

            $route = $payload['routes'][0];

            // OSRM returns [lng, lat] — flip to [lat, lng] for Leaflet
            $geometry = array_map(
                fn($c) => [(float) $c[1], (float) $c[0]],
                $route['geometry']['coordinates']
            );

            return [
                'geometry'          => $geometry,
                'distance_km'       => round($route['distance'] / 1000, 2),
                'estimated_minutes' => (int) ceil($route['duration'] / 60),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}