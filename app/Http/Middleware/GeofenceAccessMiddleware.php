<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Ceo\CeoLocation;
use Symfony\Component\HttpFoundation\Response;

class GeofenceAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. CHECK ROLE: THE CEO BYPASS
        // If the user is a CEO, they can access from any location or IP.
        if ($request->user() && $request->user()->role === 'CEO') {
            return $next($request);
        }

        // Fetch ALL active authorized zones for this user (multi-site support)
        $safeZones = CeoLocation::where('user_id', auth()->id())
            ->where('is_active', true)
            ->latest()
            ->get();

        // Fallback for DBs where the migration hasn't run yet
        if ($safeZones->isEmpty()) {
            $safeZones = CeoLocation::where('user_id', auth()->id())->latest()->take(1)->get();
        }

        // If no safe zone is set, we block access for security
        if ($safeZones->isEmpty()) {
            return $this->deny($request, 'Security Error: No authorized zone defined.');
        }

        // 2. CHECK GPS: THE PRIMARY VALIDATION (valid inside ANY active site)
        $currentLat = $request->header('X-User-Lat');
        $currentLng = $request->header('X-User-Lng');

        if ($currentLat && $currentLng) {
            foreach ($safeZones as $safeZone) {
                $distance = $this->calculateDistance(
                    $safeZone->latitude, $safeZone->longitude,
                    $currentLat, $currentLng
                );

                if ($distance <= $safeZone->range_radius) {
                    return $next($request); // GPS Validated
                }
            }
        }

        // 3. CHECK IP: THE FALLBACK VALIDATION
        // If GPS is missing or outside range, check if they are on the warehouse Wi-Fi
        $userIp = $request->ip();
        $officeIp = config('app.office_ip'); // We define this in config/app.php

        if ($userIp === $officeIp) {
            return $next($request); // Network Validated
        }

        // Also check against all active safe zone IPs as fallback
        foreach ($safeZones as $safeZone) {
            if ($safeZone->ip_address && $userIp === $safeZone->ip_address) {
                return $next($request);
            }
        }

        // 4. DENY: BOTH FAILED
        return $this->deny(
            $request,
            'Access Denied: You must be at the warehouse or connected to the office network.',
            ['reason' => 'GPS out of range or sensor timeout AND IP mismatch.', 'detected_ip' => $userIp]
        );
    }

    /**
     * Return a geofence denial in the format the caller expects:
     * JSON for API callers, redirect-with-flash for Inertia page visits
     * (AuthenticatedLayout surfaces flash.geofence_error via SweetAlert).
     */
    private function deny(Request $request, string $message, array $debug = [])
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
                'debug_info' => $debug,
            ], 403);
        }

        return redirect()->back()->with('geofence_error', $message);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}