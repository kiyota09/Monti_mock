<?php

namespace App\Http\Controllers\Hrm\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Hrm\EmployeeShift;
use App\Models\Hrm\Holiday;
use App\Support\PhilippineHolidays;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Holiday Management (HRM > Attendance workspace).
 * Big-calendar UI: click a date to add/edit the holiday on it.
 */
class HolidayController extends Controller
{
    use HasPagePermissions;

    // `special_working` is a legacy type kept for backward compatibility:
    // payroll treats it as an ordinary workday (no holiday premium). `non_working`
    // is treated the same as `special_non_working` (130% / 150% when worked).
    public const TYPES = ['regular', 'special_non_working', 'non_working', 'special_working'];

    public const POLICIES = ['non_working', 'required_to_work'];

    public function index(Request $request)
    {
        $year = (int) ($request->query('year') ?? $request->route('year') ?? now()->year);
        $dbHolidays = Holiday::orderBy('holiday_date')->get()->map(fn ($h) => $this->row($h))->values()->all();

        // Merge with Philippine holidays for the current year
        $merged = PhilippineHolidays::mergeWithCalculated($dbHolidays, $year);
        $holidays = collect($merged)->map(function ($h, $date) {
            return [
                'id' => null,
                'date' => $date,
                'name' => $h[0],
                'type' => $h[1],
                'type_label' => $this->typeLabel($h[1]),
                'policy' => $h[2],
                'policy_label' => $h[2] === 'required_to_work' ? 'Required to Work' : 'Non-Working Day',
                'status' => 'approved',
                'is_ph_holiday' => true,
            ];
        })->values();

        return Inertia::render('Dashboard/HRM_NEW/Holidays', [
            'holidays' => $holidays,
            'types' => self::TYPES,
            'policies' => self::POLICIES,
            'permissions' => $this->getPagePermissionsForModule('HRM'),
            'currentYear' => $year,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'holiday_date' => 'required|date|unique:holidays,holiday_date',
            'holiday_name' => 'required|string|max:255',
            'holiday_type' => 'required|in:' . implode(',', self::TYPES),
            'work_policy' => 'required|in:' . implode(',', self::POLICIES),
        ]);
        $this->validateCombination($data['holiday_type'], $data['work_policy']);

        $holiday = Holiday::create([
            'holiday_date' => $date,
            'holiday_name' => $data['holiday_name'],
            'holiday_type' => $data['holiday_type'],
            'work_policy' => $data['work_policy'],
            'premium_rate' => self::defaultPremium($data['holiday_type']),
            'status' => 'approved',
        ]);

        return back()->with('message', 'Holiday added.');
    }

    public function update(Request $request, Holiday $holiday)
    {
        $data = $request->validate([
            'holiday_date' => 'required|date|unique:holidays,holiday_date,' . $holiday->id,
            'holiday_name' => 'required|string|max:255',
            'holiday_type' => 'required|in:' . implode(',', self::TYPES),
            'work_policy' => 'required|in:' . implode(',', self::POLICIES),
        ]);
        $this->validateCombination($data['holiday_type'], $data['work_policy']);

        $holiday->update([
            'holiday_date' => $newDate,
            'holiday_name' => $data['holiday_name'],
            'holiday_type' => $data['holiday_type'],
            'work_policy' => $data['work_policy'],
            'premium_rate' => self::defaultPremium($data['holiday_type']),
        ]);

        return back()->with('message', 'Holiday updated.');
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return back()->with('message', 'Holiday removed.');
    }

    private function row(Holiday $h): array
    {
        return [
            'id' => $h->id,
            'date' => substr((string) $h->holiday_date, 0, 10),
            'name' => $h->holiday_name,
            'type' => $h->holiday_type,
            'type_label' => $this->typeLabel($h->holiday_type),
            'policy' => $h->work_policy ?? 'non_working',
            'policy_label' => ($h->work_policy ?? 'non_working') === 'required_to_work' ? 'Required to Work' : 'Non-Working Day',
            'status' => $h->status,
        ];
    }

    /**
     * A special working day is an ordinary workday: it must require work.
     * Storing it as `non_working` would be silently reinterpreted as an
     * ordinary day by payroll (policy ignored), so reject it explicitly.
     */
    private function validateCombination(string $type, string $policy): void
    {
        if ($type === 'special_working' && $policy !== 'required_to_work') {
            abort(response()->json([
                'message' => 'The selected holiday type is invalid.',
                'errors' => ['work_policy' => ['A special working day is an ordinary workday and must use the Required to Work policy.']],
            ], 422));
        }
    }

    /**
     * Legacy premium_rate extra-multiple by type (kept consistent for the
     * older scheduler flow: value is added on top of the base day).
     */
    private static function defaultPremium(string $type): float
    {
        return match ($type) {
            'regular' => 1.0,            // 100% base + 100% extra = 200%
            'special_non_working' => 0.3, // 100% base + 30% extra = 130%
            default => 0.0,
        };
    }

    private function typeLabel(?string $type): string
    {
        return match ($type) {
            'regular' => 'Regular',
            'special_non_working' => 'Special Non-Working Day',
            'non_working' => 'Non-Working Day',
            'special_working' => 'Special Working Day',
            default => ucfirst((string) $type),
        };
    }
}
