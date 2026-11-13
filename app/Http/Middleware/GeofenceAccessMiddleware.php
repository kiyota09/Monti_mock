<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Ceo\CeoLocation;
use Symfony\Component\HttpFoundation\Response;

class GeofenceAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Authentication must remain available so an employee can sign in
        // before the browser requests location permission.
        if (! $request->user()) {
            return $next($request);
        }

        // IT must retain access to the location-management page so it can
        // create or adjust the company work zones that protect other routes.
        if ($request->is('dashboard/it/location*')) {
            return $next($request);
        }

        // The dashboard is the location-permission bootstrap page. It must
        // render once so the browser can request GPS permission and attach
        // coordinates to subsequent internal requests.
        if ($request->is('dashboard') && $request->isMethod('GET')
            && ! $request->header('X-User-Lat') && ! $request->header('X-User-Lng')) {
            return $next($request);
        }

        // 1. CHECK ROLE: THE CEO BYPASS
        // If the user is a CEO, they can access from any location or IP.
        if ($request->user() && $request->user()->role === 'CEO') {
            return $next($request);
        }

        // Locations configured by IT are shared company work sites. They
        // apply to every internal employee, not only to the IT user who
        // created the location.
        $safeZones = CeoLocation::where('is_active', true)->latest()->get();

        // If no safe zone is set, we block access for security
        if ($safeZones->isEmpty()) {
            return $this->deny($request, 'Security Error: No authorized zone defined.');
        }

        // 2. CHECK GPS: THE PRIMARY VALIDATION (valid inside ANY active site)
        $currentLat = $request->header('X-User-Lat');
        $currentLng = $request->header('X-User-Lng');
        $hasGpsHeaders = $currentLat !== null || $currentLng !== null;

        if ($currentLat && $currentLng) {
            foreach ($safeZones as $safeZone) {
                $distance = self::calculateDistance(
                    $safeZone->latitude, $safeZone->longitude,
                    $currentLat, $currentLng
                );

                if ($distance <= $safeZone->range_radius) {
                    // Remember only a location that passed the safe-zone
                    // check. A full browser refresh cannot attach custom GPS
                    // headers, so it may reuse this recent verified fix.
                    $request->session()->put('geofence.last_verified_location', [
                        'lat' => (float) $currentLat,
                        'lng' => (float) $currentLng,
                        'verified_at' => time(),
                    ]);
                    return $next($request); // GPS Validated
                }
            }
        }

        // Browser reloads are ordinary document requests and cannot include
        // the X-User-Lat / X-User-Lng headers used by Inertia/Axios. Recheck
        // the last verified fix against today's active safe zones for up to
        // 10 minutes, without extending its age on repeated reloads.
        if (! $hasGpsHeaders && $request->hasSession()) {
            $lastVerified = $request->session()->get('geofence.last_verified_location');
            $verifiedAt = (int) ($lastVerified['verified_at'] ?? 0);
            if (is_array($lastVerified)
                && $verifiedAt > 0
                && time() - $verifiedAt <= 600
                && isset($lastVerified['lat'], $lastVerified['lng'])) {
                foreach ($safeZones as $safeZone) {
                    $distance = self::calculateDistance(
                        $safeZone->latitude, $safeZone->longitude,
                        (float) $lastVerified['lat'], (float) $lastVerified['lng']
                    );

                    if ($distance <= $safeZone->range_radius) {
                        return $next($request);
                    }
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

    /** Validate a login request before an employee session is created. */
    public static function allows(Request $request): bool
    {
        $user = $request->user();
        if ($user && $user->role === 'CEO') {
            return true;
        }

        $zones = CeoLocation::where('is_active', true)->latest()->get();
        if ($zones->isEmpty()) return false;

        $lat = $request->header('X-User-Lat');
        $lng = $request->header('X-User-Lng');
        if ($lat !== null && $lng !== null) {
            foreach ($zones as $zone) {
                if (self::calculateDistance($zone->latitude, $zone->longitude, $lat, $lng) <= $zone->range_radius) {
                    return true;
                }
            }
        }

        $ip = $request->ip();
        if ($ip === config('app.office_ip')) return true;
        return $zones->contains(fn ($zone) => $zone->ip_address && $zone->ip_address === $ip);
    }

    /**
     * Return a geofence denial in the format the caller expects:
     * JSON for API callers, redirect-with-flash for Inertia page visits
     * (AuthenticatedLayout surfaces flash.geofence_error via the shared modal).
     */
    private function deny(Request $request, string $message, array $debug = [])
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
                'debug_info' => $debug,
            ], 403);
        }

        // End the internal employee session when the device is outside all
        // permitted sites so the login page remains reachable.
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->with('geofence_error', $message);
    }

    private static function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
