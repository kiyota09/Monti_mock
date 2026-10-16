<?php

namespace App\Http\Controllers\Hrm\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Hrm\HrmEmploymentType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmploymentTypeController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard/HRM_NEW/EmploymentTypes', [
            'employmentTypes' => HrmEmploymentType::latest()->get(),
            'archivedCount' => HrmEmploymentType::whereNotNull('archived_at')->count(),
            'activeCount' => HrmEmploymentType::whereNull('archived_at')->where('is_active', true)->count(),
            'totalCount' => HrmEmploymentType::count(),
        ]);
    }

    /**
     * Active employment types for dropdowns/selection (ported from super_monti).
     */
    public function getActive()
    {
        return response()->json(
            HrmEmploymentType::where('is_active', true)
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get(['id', 'name', 'description'])
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:hrm_employment_types,name',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        $type = HrmEmploymentType::create($data);

        if ($request->wantsJson()) {
            return response()->json($type, 201);
        }

        return back()->with('success', 'Employment type created.');
    }

    public function update(Request $request, HrmEmploymentType $employmentType)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255|unique:hrm_employment_types,name,' . $employmentType->id,
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        $employmentType->update($data);

        if ($request->wantsJson()) {
            return response()->json($employmentType);
        }

        return back()->with('success', 'Employment type updated.');
    }

    public function destroy(Request $request, HrmEmploymentType $employmentType)
    {
        $employmentType->update(['archived_at' => now(), 'is_active' => false]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Employment type archived.');
    }

    public function restore(Request $request, HrmEmploymentType $employmentType)
    {
        $employmentType->update(['archived_at' => null, 'is_active' => true]);

        if ($request->wantsJson()) {
            return response()->json($employmentType);
        }

        return back()->with('success', 'Employment type restored.');
    }
}
