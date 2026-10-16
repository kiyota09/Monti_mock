<?php

namespace App\Http\Controllers\Man\Staff;

use App\Http\Controllers\Controller;
use App\Models\Hrm\EmployeeShift;
use App\Models\Man\Machine;
use App\Models\Man\MachineReport;
use Illuminate\Support\Facades\Auth;

abstract class ManufacturingStaffController extends Controller
{
    /**
     * Get the currently authenticated staff member.
     *
     * @return \App\Models\Core\User
     */
    protected function staff()
    {
        return Auth::user();
    }

    /**
     * Get the staff's current shift.
     * If a shift field exists on the user, it uses that; otherwise,
     * it tries to fetch the shift from the employee_shifts table for today.
     *
     * @return string
     */
    protected function getShift()
    {
        $user = $this->staff();

        // Option 1: if the user has a direct 'shift' attribute
        if (isset($user->shift)) {
            return $user->shift;
        }

        // Option 2: fetch from employee_shifts table
        $today = now()->toDateString();
        $shift = EmployeeShift::where('user_id', $user->id)
            ->where('effective_date', $today)
            ->first();

        return $shift ? $shift->shift_type : 'Morning'; // fallback
    }

    /**
     * Generate a unique code for a record.
     * Format: PREFIX-YYYY-MM-XXXXX (e.g., FABRIC-2026-03-00001)
     *
     * @param  string  $prefix
     * @param  string  $modelClass  Fully qualified model class name
     * @param  string  $codeColumn  Column name where the code is stored
     * @return string
     */
    protected function generateCode($prefix, $modelClass, $codeColumn = 'code')
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $prefix = strtoupper($prefix);

        $lastCode = $modelClass::where($codeColumn, 'LIKE', "{$prefix}-{$year}-{$month}-%")
            ->orderBy($codeColumn, 'desc')
            ->first();

        if ($lastCode) {
            $parts = explode('-', $lastCode->{$codeColumn});
            $lastNumber = (int) end($parts);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%s-%05d', $prefix, $year, $month, $newNumber);
    }

    /**
     * Personal efficiency summary for the logged-in staff member.
     *
     * Access standard: every count here is scoped to operator_id = self,
     * except the read-only machine-availability context for the staff's
     * own machine type. Safe to expose on any staff dashboard.
     *
     * @param  string  $jobModel   e.g. Fabric::class (must have operator_id + date column)
     * @param  string  $dateColumn e.g. processed_at / packaged_at
     */
    protected function staffEfficiency(string $jobModel, string $dateColumn = 'processed_at'): array
    {
        $staffId = $this->staff()->id;

        return [
            'my_today' => $jobModel::where('operator_id', $staffId)
                ->whereDate($dateColumn, today())->count(),
            'my_week' => $jobModel::where('operator_id', $staffId)
                ->whereBetween($dateColumn, [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'my_total' => $jobModel::where('operator_id', $staffId)->count(),
            'my_open_reports' => MachineReport::where('reported_by', $staffId)
                ->where('status', 'pending')->count(),
            'my_resolved_reports' => MachineReport::where('reported_by', $staffId)
                ->where('status', 'resolved')->count(),
        ];
    }

    /**
     * Read-only availability of the staff's own machine type.
     * Returns null when the role has no dedicated machines (e.g. ironing/packaging).
     */
    protected function machineAvailability(?string $machineType): ?array
    {
        if (! $machineType) {
            return null;
        }

        $total = Machine::where('type', $machineType)->count();

        return [
            'type' => $machineType,
            'total' => $total,
            'available' => Machine::where('type', $machineType)->where('status', 'available')->count(),
            'down' => Machine::where('type', $machineType)->where('status', '!=', 'available')->count(),
        ];
    }

    /**
     * Shared personal work-history query (own jobs only).
     *
     * @param  string  $jobModel
     * @param  array   $with
     * @param  string  $dateColumn
     */
    protected function staffHistory(string $jobModel, array $with = [], string $dateColumn = 'processed_at')
    {
        $query = $jobModel::with($with)->where('operator_id', $this->staff()->id);

        if ($search = request('search')) {
            $query->where('code', 'like', "%{$search}%");
        }
        if ($from = request('from')) {
            $query->whereDate($dateColumn, '>=', $from);
        }
        if ($to = request('to')) {
            $query->whereDate($dateColumn, '<=', $to);
        }

        return $query->latest()->paginate(15)->withQueryString();
    }
}
