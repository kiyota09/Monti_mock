<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\It\ItAsset;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $query = ItAsset::with('assignee:id,name');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('status')) {
            $request->status === 'expiring'
                ? $query->expiring()
                : ($request->status === 'expired'
                    ? $query->expired()
                    : $query->where('status', $request->status));
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Dashboard/IT/Manager/Assets', [
            'assets' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only(['category', 'status', 'search']),
            'stats' => [
                'total' => ItAsset::count(),
                'inUse' => ItAsset::where('status', 'in_use')->count(),
                'available' => ItAsset::where('status', 'available')->count(),
                'maintenance' => ItAsset::where('status', 'maintenance')->count(),
                'expiring' => ItAsset::expiring()->count(),
                'expired' => ItAsset::expired()->count(),
            ],
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|in:hardware,software,network,peripheral',
            'type' => 'required|string|max:100',
            'serial_number' => 'nullable|string|max:255',
            'specs' => 'nullable|string|max:2000',
            'location' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'warranty_end' => 'nullable|date|after_or_equal:purchase_date',
            'vendor' => 'nullable|string|max:255',
            'cost' => 'nullable|numeric|min:0|max:9999999999.99',
            'notes' => 'nullable|string|max:2000',
        ]);

        $seq = ItAsset::where('asset_code', 'like', 'AST-'.now()->format('Y').'-%')->count() + 1;
        $data['asset_code'] = sprintf('AST-%s-%04d', now()->format('Y'), $seq);

        ItAsset::create($data);

        return redirect()->back()->with('success', "Asset {$data['asset_code']} registered.");
    }

    public function update(Request $request, ItAsset $asset)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|in:hardware,software,network,peripheral',
            'type' => 'required|string|max:100',
            'serial_number' => 'nullable|string|max:255',
            'specs' => 'nullable|string|max:2000',
            'location' => 'nullable|string|max:255',
            'status' => 'required|in:available,in_use,maintenance,retired',
            'purchase_date' => 'nullable|date',
            'warranty_end' => 'nullable|date|after_or_equal:purchase_date',
            'vendor' => 'nullable|string|max:255',
            'cost' => 'nullable|numeric|min:0|max:9999999999.99',
            'notes' => 'nullable|string|max:2000',
        ]);

        $asset->update($data);

        return redirect()->back()->with('success', "Asset {$asset->asset_code} updated.");
    }

    /**
     * Assign an asset to a user (records assignment history).
     */
    public function assign(Request $request, ItAsset $asset)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($asset->status === 'retired') {
            return redirect()->back()->withErrors(['asset' => 'Retired assets cannot be assigned.']);
        }

        // Close any open assignment row for this asset first.
        $asset->assignments()->whereNull('returned_at')->update(['returned_at' => now()]);

        $asset->assignments()->create([
            'user_id' => $data['user_id'],
            'assigned_by' => auth()->id(),
            'notes' => $data['notes'] ?? null,
        ]);

        $asset->update(['status' => 'in_use', 'assigned_to_user_id' => $data['user_id']]);

        return redirect()->back()->with('success', "Asset {$asset->asset_code} assigned.");
    }

    /**
     * Return an asset to the pool.
     */
    public function returnAsset(ItAsset $asset)
    {
        $asset->assignments()->whereNull('returned_at')->update(['returned_at' => now()]);
        $asset->update(['status' => 'available', 'assigned_to_user_id' => null]);

        return redirect()->back()->with('success', "Asset {$asset->asset_code} returned to pool.");
    }

    public function destroy(ItAsset $asset)
    {
        if ($asset->status === 'in_use') {
            return redirect()->back()->withErrors(['asset' => 'Return the asset before deleting it.']);
        }

        $asset->delete();

        return redirect()->back()->with('success', 'Asset record deleted.');
    }
}
