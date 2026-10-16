<?php

namespace App\Http\Controllers\Hrm\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Hrm\EmployeeShift;
use App\Models\Hrm\Holiday;
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

    public const TYPES = ['regular', 'special_non_working', 'non_working'];

    public const POLICIES = ['non_working', 'required_to_work'];

    public function index()
    {
        $holidays = Holiday::orderBy('holiday_date')->get()->map(fn ($h) => $this->row($h))->values();

        return Inertia::render('Dashboard/HRM_NEW/Holidays', [
            'holidays' => $holidays,
            'types' => self::TYPES,
            'policies' => self::POLICIES,
            'permissions' => $this->getPagePermissionsForModule('HRM'),
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

        $date = substr((string) $data['holiday_date'], 0, 10);

        // Non-working holidays clear any shift scheduled that day.
        EmployeeShift::where('effective_date', $date)->delete();

        Holiday::create([
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

        $oldDate = substr((string) $holiday->holiday_date, 0, 10);
        $newDate = substr((string) $data['holiday_date'], 0, 10);

        if ($oldDate !== $newDate) {
            EmployeeShift::where('effective_date', $newDate)->delete();
        }

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
