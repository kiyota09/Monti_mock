<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Traits\HasPagePermissions;

class OnboardingController extends Controller
{
    use HasPagePermissions;

    /**
     * Display the onboarding page with a list of all company trainees.
     * Accessible by HRM, CEO, Secretary, and Special Officers.
     */
    public function index()
    {
        // Fetches all active trainees across ALL modules.
        // Because the Dept Manager left their position as 'trainee', they will successfully show up here!
        $trainees = User::where('position', 'trainee')
            ->where('is_active', true)
            ->with('traineeGrade')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'email' => $t->email,
                'role' => $t->role, // Shows which department they are training in
                'join_date' => $t->join_date,
                'grade_percentage' => $t->traineeGrade?->total_percentage ?? 0,
            ]);

        // Get page permissions for the current user (HRM module)
        $permissions = $this->getPagePermissionsForModule('HRM');

        return Inertia::render('Dashboard/HRM_NEW/OnboardingLegacy', [
            'trainees' => $trainees,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Convert a trainee to a permanent staff member.
     * Only allowed if the trainee has a grade of 80% or above.
     */
    public function convert(Request $request, $id)
    {
        $trainee = User::with('traineeGrade')->findOrFail($id);

        // Validate that the trainee has a grade and it meets the threshold
        $grade = $trainee->traineeGrade?->total_percentage ?? 0;

        if ($grade < 80) {
            return back()->withErrors([
                'grade' => 'Cannot convert trainee with grade below 80%. Current grade: '.$grade.'%',
            ]);
        }

        // OFFICIAL CONVERSION: This makes them an official employee.
        // It changes their position to 'staff', which automatically removes them from
        // BOTH the Department Manager's Trainee list and the HR Onboarding list!
        $trainee->update(['position' => 'staff']);

        return back()->with('message', 'Trainee converted to official staff successfully.');
    }
}