<?php

namespace App\Http\Controllers\Vp;

use App\Http\Controllers\Controller;
use App\Models\Vp\ExecutiveDirective;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VpDirectiveController extends Controller
{
    public function index(Request $request)
    {
        $query = ExecutiveDirective::with('creator:id,name')->latest();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return Inertia::render('Dashboard/VP/Directives', [
            'directives' => $query->paginate(20)->withQueryString(),
            'filter' => $request->get('status', 'all'),
            'counts' => [
                'open' => ExecutiveDirective::where('status', 'open')->count(),
                'in_progress' => ExecutiveDirective::where('status', 'in_progress')->count(),
                'done' => ExecutiveDirective::where('status', 'done')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'assigned_to' => 'required|string|max:64',
            'due_date' => 'nullable|date|after_or_equal:today',
            'priority' => 'required|in:low,normal,high,urgent',
        ]);

        ExecutiveDirective::create([
            ...$validated,
            'status' => 'open',
            'created_by' => auth()->id(),
        ]);

        return back()->with('message', 'Directive issued successfully.');
    }

    /**
     * Advance directive workflow (forward-only).
     */
    public function update(Request $request, ExecutiveDirective $directive)
    {
        $validated = $request->validate([
            'status' => 'required|in:in_progress,done',
            'completion_notes' => 'nullable|string',
        ]);

        $order = ['open' => 0, 'in_progress' => 1, 'done' => 2, 'overdue' => 1];
        if ($order[$validated['status']] < ($order[$directive->status] ?? 0)) {
            return back()->withErrors(['status' => 'Directives cannot move backwards.']);
        }

        $directive->update([
            'status' => $validated['status'],
            'completion_notes' => $validated['completion_notes'] ?? $directive->completion_notes,
        ]);

        return back()->with('message', 'Directive updated successfully.');
    }

    public function destroy(ExecutiveDirective $directive)
    {
        $directive->delete();

        return back()->with('message', 'Directive removed.');
    }
}
