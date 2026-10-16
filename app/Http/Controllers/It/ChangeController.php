<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\It\ItChange;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChangeController extends Controller
{
    public function index(Request $request)
    {
        $query = ItChange::with(['implementer:id,name', 'approver:id,name']);

        if ($request->filled('status')) {
            $request->status === 'open'
                ? $query->open()
                : $query->where('status', $request->status);
        }
        if ($request->filled('change_type')) {
            $query->where('change_type', $request->change_type);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('change_no', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Dashboard/IT/Manager/Changes', [
            'changes' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only(['status', 'change_type', 'search']),
            'isManager' => auth()->user()->position === 'manager',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'change_type' => 'required|in:standard,normal,emergency',
            'risk' => 'required|in:low,medium,high',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after_or_equal:scheduled_start',
            'rollback_plan' => 'nullable|string|max:3000',
        ]);

        $seq = ItChange::where('change_no', 'like', 'CHG-'.now()->format('Y').'-%')->count() + 1;

        $change = ItChange::create([
            ...$data,
            'change_no' => sprintf('CHG-%s-%04d', now()->format('Y'), $seq),
            // Standard (pre-approved) changes skip CAB; everything else needs approval.
            'status' => $data['change_type'] === 'standard' ? 'approved' : 'pending_approval',
            'implemented_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', "Change {$change->change_no} filed for CAB review.");
    }

    public function approve(ItChange $change)
    {
        if (! in_array($change->status, ['pending_approval', 'draft'], true)) {
            return redirect()->back()->withErrors(['status' => 'Only draft or pending changes can be approved.']);
        }

        $change->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return redirect()->back()->with('success', "Change {$change->change_no} approved.");
    }

    public function reject(Request $request, ItChange $change)
    {
        $data = $request->validate(['completion_notes' => 'nullable|string|max:2000']);

        if (! in_array($change->status, ['pending_approval', 'draft'], true)) {
            return redirect()->back()->withErrors(['status' => 'Only draft or pending changes can be rejected.']);
        }

        $change->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'completion_notes' => $data['completion_notes'] ?? null,
        ]);

        return redirect()->back()->with('success', "Change {$change->change_no} rejected.");
    }

    /**
     * Advance implementation: approved → in_progress → completed / rolled_back.
     */
    public function setStatus(Request $request, ItChange $change)
    {
        $transitions = [
            'approved' => ['in_progress'],
            'in_progress' => ['completed', 'rolled_back'],
        ];

        $data = $request->validate([
            'status' => 'required|in:in_progress,completed,rolled_back',
            'completion_notes' => 'nullable|string|max:2000',
        ]);

        if (! in_array($data['status'], $transitions[$change->status] ?? [], true)) {
            return redirect()->back()->withErrors([
                'status' => "Cannot move change from {$change->status} to {$data['status']}.",
            ]);
        }

        $change->update([
            'status' => $data['status'],
            'completion_notes' => $data['completion_notes'] ?? $change->completion_notes,
        ]);

        return redirect()->back()->with('success', "Change {$change->change_no} is now {$data['status']}.");
    }

    public function destroy(ItChange $change)
    {
        if (! in_array($change->status, ['draft', 'rejected'], true)) {
            return redirect()->back()->withErrors(['change' => 'Only draft or rejected changes can be deleted.']);
        }

        $change->delete();

        return redirect()->back()->with('success', 'Change request deleted.');
    }
}
