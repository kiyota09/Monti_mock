<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\EmployeeShift;
use App\Models\Ceo\CeoLocation; 
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClockController extends Controller
{
    public function clock()
    {
        $user = Auth::user();
        $now = Carbon::now('Asia/Manila');
        $today = $now->toDateString();

        // Fetch geofence settings to pass to the frontend (all active company sites)
        $geofence = CeoLocation::where('user_id', $user->id)->latest()->first();
        $geofenceZones = CeoLocation::where('user_id', $user->id)
            ->where('is_active', true)
            ->latest()
            ->get();

        return Inertia::render('Dashboard/USERS/clock', [
            'today_log' => AttendanceLog::where('user_id', $user->id)
                ->where('date', $today)
                ->first(),

            'assigned_shift' => EmployeeShift::where('user_id', $user->id)
                ->where('effective_date', $today)
                ->first(),

            'history' => AttendanceLog::where('user_id', $user->id)
                ->orderBy('date', 'desc')
                ->take(5)
                ->get(),
            
            // Pass DB geofence settings to Vue (single for compat + full zone list)
            'geofence_settings' => $geofence,
            'geofence_zones' => $geofenceZones,
        ]);
    }

    public function toggle(Request $request)
    {
        $user = Auth::user();
        $now = Carbon::now('Asia/Manila');
        $today = $now->toDateString();

        // 1. DATABASE GEOFENCE CHECK
        if ($user->role !== 'CEO') {
            $currentLat = $request->header('X-User-Lat');
            $currentLng = $request->header('X-User-Lng');

            $safeZones = CeoLocation::where('user_id', $user->id)
                ->where('is_active', true)
                ->latest()
                ->get();

            // Fallback to the latest pin when the is_active column
            // doesn't exist yet (migration not run).
            if ($safeZones->isEmpty()) {
                $safeZones = CeoLocation::where('user_id', $user->id)->latest()->take(1)->get();
            }

            if ($safeZones->isEmpty()) {
                return redirect()->back()->with('error', 'Security Error: No authorized work location found in database.');
            }

            if (!$currentLat || !$currentLng) {
                return redirect()->back()->with('error', 'GPS Required: Please enable location services.');
            }

            $nearest = null;
            $nearestLabel = null;
            foreach ($safeZones as $zone) {
                $distance = $this->calculateDistance(
                    (float) $zone->latitude,
                    (float) $zone->longitude,
                    (float) $currentLat,
                    (float) $currentLng
                );
                if ($nearest === null || $distance < $nearest) {
                    $nearest = $distance;
                    $nearestLabel = $zone->label ?: ($zone->place_name ?: 'work site');
                }
                // Clock-in/out succeeds from ANY active site
                if ($distance <= $zone->range_radius) {
                    $nearest = $distance;
                    $nearestLabel = $zone->label ?: ($zone->place_name ?: 'work site');
                    $nearest = -1; // marker for "inside a zone"
                    break;
                }
            }

            // Compare against DB 'range_radius' of every active site
            if ($nearest !== -1) {
                $allowed = $safeZones->max('range_radius');
                return redirect()->back()->with('error', "Out of Range: You are " . round($nearest) . "m from the nearest site ({$nearestLabel}). Max allowed is " . $allowed . "m.");
            }
        }

        // ... rest of your clock in/out logic
        $timeString12hr = $now->format('h:i A');
        $shift = EmployeeShift::where('user_id', $user->id)->where('effective_date', $today)->first();
        $log = AttendanceLog::where('user_id', $user->id)->where('date', $today)->first();

        if (!$log) {
            if (!$shift) return redirect()->back()->with('error', 'No shift assigned.');
            
            AttendanceLog::create([
                'user_id' => $user->id,
                'date' => $today,
                'clock_in' => $timeString12hr,
                'status' => ($now->gt(Carbon::parse($shift->start_time))) ? 'Late' : 'On-Time',
            ]);
            return redirect()->back()->with('success', 'Clocked in successfully.');
        } elseif ($log && !$log->clock_out) {
            $log->update(['clock_out' => $timeString12hr]);
            return redirect()->back()->with('success', 'Clocked out successfully.');
        }

        return redirect()->back();
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