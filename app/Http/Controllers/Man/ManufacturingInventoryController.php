<?php

namespace App\Http\Controllers\Man;

use App\Http\Controllers\Controller;
use App\Models\Man\ManufacturingInventoryItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class ManufacturingInventoryController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Build base query — never show depleted items
        $query = ManufacturingInventoryItem::with('material')
            ->where('status', '!=', 'depleted')
            ->orderBy('received_at', 'desc');

        // ─────────────────────────────────────────────────────────────────
        // DEPARTMENT FILTER LOGIC
        //
        // No manufacturing manager exists: the module is handled by 4
        // department supervisors (knitting, dyeing, finishing, maintenance).
        // Elevated oversight (CEO, secretary, GM) sees ALL departments.
        // Supervisors see only their supervised department (finishing has no
        // inventory department by design, so it yields zero rows).
        // Regular staff see only the department derived from their role.
        // ─────────────────────────────────────────────────────────────────
        $isElevatedUser = in_array($user->position, ['secretary', 'special_officer']);

        if (!$isElevatedUser) {
            // Supervisors: filter by their assigned department
            if ($user->is_manufacturing_supervisor && $user->supervisor_department) {
                $query->whereRaw('LOWER(department) = ?', [strtolower($user->supervisor_department)]);
            }
            // Regular staff: derive department from their role prefix
            elseif ($user->manufacturing_role) {
                $dept = strtolower(explode('_', $user->manufacturing_role)[0] ?? '');
                if ($dept) {
                    $query->whereRaw('LOWER(department) = ?', [$dept]);
                }
            }
        }
        // Elevated users: no WHERE clause added → all departments visible

        $items = $query->get()->map(function ($item) {
            // Safely format received_at
            $receivedAt = null;
            if ($item->received_at) {
                try {
                    $receivedAt = Carbon::parse($item->received_at)->format('Y-m-d H:i');
                } catch (\Exception $e) {
                    $receivedAt = $item->received_at;
                }
            }

            // Guard against missing material relationship
            $materialName = $item->material ? $item->material->name : '(Unknown Material)';

            return [
                'id'                 => $item->id,
                'control_number'     => $item->control_number,
                'material_name'      => $materialName,
                'category'           => $item->category,
                'initial_quantity'   => $item->initial_quantity,
                'remaining_quantity' => $item->remaining_quantity,
                'unit'               => $item->unit,
                'status'             => $item->status,
                'total_units'        => $item->total_units,
                'used_units'         => $item->used_units,
                'unit_type'          => $item->unit_type,
                'unit_weight'        => $item->unit_weight,
                'department'         => $item->department,
                'received_at'        => $receivedAt,
                'notes'              => $item->notes,
            ];
        });

        return Inertia::render('Dashboard/MAN/Inventory/ProductionInventory', [
            'items' => $items,
        ]);
    }

    public function updateContainer(Request $request, ManufacturingInventoryItem $item)
    {
        $validated = $request->validate([
            'total_units' => 'required|integer|min:1',
            'unit_type'   => 'required|in:roll,box',
            'unit_weight' => 'nullable|numeric|min:0',
        ]);

        // Case-insensitive category check for Yarn
        if (strtolower($item->category) === 'yarn' && empty($validated['unit_weight'])) {
            return back()->withErrors(['unit_weight' => 'Unit weight is required for yarn.']);
        }

        $item->update([
            'total_units'        => $validated['total_units'],
            'unit_type'          => $validated['unit_type'],
            'unit_weight'        => $validated['unit_weight'] ?? null,
            'used_units'         => 0,
            'remaining_quantity' => $item->initial_quantity,
            'status'             => 'available',
        ]);

        return back()->with('success', 'Container details saved.');
    }

    public function consume(Request $request, ManufacturingInventoryItem $item)
    {
        $validated = $request->validate([
            'units_used' => 'required|integer|min:1',
        ]);

        if (!$item->total_units) {
            return back()->withErrors(['units_used' => 'Container not opened yet. Please set container details first.']);
        }

        if (!$item->consumeUnits($validated['units_used'])) {
            return back()->withErrors(['units_used' => 'Not enough units available.']);
        }

        return back()->with('success', "{$validated['units_used']} {$item->unit_type}(s) consumed.");
    }
}