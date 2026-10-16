<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\Core\ProblemReport;
use App\Models\Core\User;
use App\Models\It\ItTicket;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketController extends Controller
{
    /**
     * Service-desk queue. Managers see everything; staff see tickets they
     * requested or are assigned to.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isManager = $user->position === 'manager';

        $query = ItTicket::with(['assignee:id,name', 'requester:id,name'])
            ->withCount('comments');

        if (! $isManager) {
            $query->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)
                    ->orWhere('assignee_id', $user->id);
            });
        }

        if ($request->filled('status')) {
            $request->status === 'open'
                ? $query->open()
                : $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('assignee_id')) {
            $query->where('assignee_id', $request->assignee_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ticket_no', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Dashboard/IT/Manager/Tickets', [
            'tickets' => $query->orderByRaw("FIELD(priority, 'P1', 'P2', 'P3', 'P4')")
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'filters' => $request->only(['status', 'priority', 'category', 'assignee_id', 'search']),
            'itStaff' => User::where('role', 'IT')->orderBy('name')->get(['id', 'name']),
            'isManager' => $isManager,
        ]);
    }

    /**
     * Log a new incident / service request. SLA clock starts on creation.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'category' => 'required|in:incident,service_request',
            'system_area' => 'required|in:erp,network,hardware,software,plant_ot,peripheral,access',
            'priority' => 'required|in:P1,P2,P3,P4',
            'location' => 'nullable|string|max:255',
            'requester_name' => 'nullable|string|max:255',
            'assignee_id' => 'nullable|exists:users,id',
        ]);

        $prefix = $data['category'] === 'incident' ? 'INC' : 'SRV';
        $year = now()->format('Y');
        $seq = ItTicket::where('ticket_no', 'like', "{$prefix}-{$year}-%")->count() + 1;

        $ticket = ItTicket::create([
            ...$data,
            'ticket_no' => sprintf('%s-%s-%04d', $prefix, $year, $seq),
            'requester_id' => auth()->id(),
            'sla_due_at' => now()->addHours(ItTicket::SLA_HOURS[$data['priority']]),
        ]);

        return redirect()->back()->with('success', "Ticket {$ticket->ticket_no} logged. SLA due {$ticket->sla_due_at->format('M d, Y H:i')}.");
    }

    /**
     * Guarded status transitions + assignment / priority changes.
     */
    public function update(Request $request, ItTicket $ticket)
    {
        $transitions = [
            'open' => ['assigned', 'in_progress', 'closed'],
            'assigned' => ['in_progress', 'pending', 'open'],
            'in_progress' => ['pending', 'resolved', 'assigned'],
            'pending' => ['in_progress', 'resolved'],
            'resolved' => ['closed', 'reopened'],
            'reopened' => ['assigned', 'in_progress'],
            'closed' => [],
        ];

        $data = $request->validate([
            'status' => 'sometimes|in:open,assigned,in_progress,pending,resolved,closed,reopened',
            'assignee_id' => 'nullable|exists:users,id',
            'priority' => 'sometimes|in:P1,P2,P3,P4',
        ]);

        if (isset($data['status']) && $data['status'] !== $ticket->status) {
            if (! in_array($data['status'], $transitions[$ticket->status] ?? [], true)) {
                return redirect()->back()->withErrors([
                    'status' => "Cannot move ticket from {$ticket->status} to {$data['status']}.",
                ]);
            }
            if ($data['status'] === 'resolved') {
                $ticket->resolved_at = now();
            }
            if ($data['status'] === 'closed') {
                $ticket->closed_at = now();
                $ticket->resolved_at ??= now();
            }
            if ($data['status'] === 'reopened') {
                $ticket->resolved_at = null;
                $ticket->closed_at = null;
            }
        }

        $ticket->fill($data)->save();

        // A ticket born from a Help Center report keeps that report in step,
        // so the reporter watches real helpdesk progress from My reports.
        ProblemReport::where('ticket_id', $ticket->id)->update([
            'status' => match ($ticket->status) {
                'resolved', 'closed' => 'resolved',
                'open' => 'open',
                default => 'in_progress',
            },
        ]);

        return redirect()->back()->with('success', "Ticket {$ticket->ticket_no} updated.");
    }

    /**
     * Add a work-log entry / requester update to a ticket.
     */
    public function comment(Request $request, ItTicket $ticket)
    {
        $data = $request->validate([
            'body' => 'required|string|max:2000',
            'is_internal' => 'sometimes|boolean',
        ]);

        // Only managers may file internal notes; staff comments are visible.
        if (auth()->user()->position !== 'manager') {
            $data['is_internal'] = false;
        }

        $ticket->comments()->create([
            'user_id' => auth()->id(),
            'body' => $data['body'],
            'is_internal' => $data['is_internal'] ?? false,
        ]);

        return redirect()->back()->with('success', 'Update posted to ticket.');
    }

    /**
     * Delete a ticket (manager only via route middleware). Comments cascade.
     */
    public function destroy(ItTicket $ticket)
    {
        $ticket->delete();

        return redirect()->back()->with('success', 'Ticket deleted.');
    }
}
