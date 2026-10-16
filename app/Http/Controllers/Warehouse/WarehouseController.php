<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\War\Warehouse;
use App\Models\Core\User;
use App\Models\Core\UserModuleAccess;
use App\Models\War\WarehouseStockItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    /**
     * Display a listing of warehouses and eligible supervisors.
     */
    public function index()
    {
        $user = auth()->user();

        // 1. Fetch warehouses based on permissions
        if (in_array($user->position, ['secretary', 'special_officer'])) {
            // High-level users see all warehouses
            $warehouses = Warehouse::with('supervisor')->orderBy('name')->get();
        } else {
            // Supervisors only see warehouses they are assigned to
            $warehouses = Warehouse::with('supervisor')
                ->where('supervisor_id', $user->id)
                ->get();
        }

        // 2. Identify users eligible to be assigned as Warehouse Supervisors
        // Users must have 'WAR' (Warehouse) module access
        $warAccessUserIds = UserModuleAccess::where('module', 'WAR')
            ->pluck('user_id')
            ->toArray();

        // Include management roles even if they don't have explicit module rows
        $elevatedUserIds = User::whereIn('position', ['secretary', 'special_officer'])
            ->pluck('id')
            ->toArray();

        $eligibleIds = array_unique(array_merge($warAccessUserIds, $elevatedUserIds));

        $eligibleUsers = User::whereIn('id', $eligibleIds)
            ->orderBy('name')
            ->get(['id', 'name', 'position']);

        return Inertia::render('Dashboard/Warehouse/Index', [
            'warehouses' => $warehouses,
            'users' => $eligibleUsers,
        ]);
    }

    /**
     * Store a newly created warehouse.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'location'      => 'required|string|max:255',
            'supervisor_id' => 'nullable|exists:users,id',
            'color'         => 'nullable|string|in:blue,emerald,amber,violet,rose,cyan'
        ]);

        // Set default aesthetic color if none selected
        $data['color'] = $data['color'] ?? 'blue';

        Warehouse::create($data);

        return redirect()->back()->with('success', 'Warehouse created successfully.');
    }

    /**
     * Update the specified warehouse.
     */
    public function update(Request $request, Warehouse $warehouse)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'location'      => 'required|string|max:255',
            'supervisor_id' => 'nullable|exists:users,id',
            'color'         => 'nullable|string'
        ]);

        $warehouse->update($data);

        return redirect()->back()->with('success', 'Warehouse updated successfully.');
    }

    /**
     * Display warehouse monitor/layout view.
     */
    public function monitor($id)
    {
        $warehouse = Warehouse::with(['supervisor', 'sections.stockItems.material'])
            ->findOrFail($id);

        return Inertia::render('Dashboard/Warehouse/Monitor', [
            'warehouse' => $warehouse
        ]);
    }

    /**
     * Remove the warehouse only if it's empty and has no history.
     */
    public function destroy(Warehouse $warehouse)
    {
        // Safety Check: Prevent deletion if warehouse has recorded activity
        
        // 1. Check for incoming receiving logs
        $hasReceivings = $warehouse->receivings()->exists();
        
        // 2. Check for physical stock remaining
        $hasStock = WarehouseStockItem::where('warehouse_id', $warehouse->id)
            ->where('quantity', '>', 0)
            ->exists();

        if ($hasReceivings || $hasStock) {
            return back()->withErrors([
                'error' => 'Action Denied: This warehouse is currently in use. It contains active stock or historical receiving data that must be archived or moved first.'
            ]);
        }

        $warehouse->delete();

        return redirect()->back()->with('success', 'Warehouse removed from system.');
    }
}