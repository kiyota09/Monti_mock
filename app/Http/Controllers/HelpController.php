<?php

namespace App\Http\Controllers;

use App\Models\Core\ProblemReport;
use App\Models\It\ItTicket;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class HelpController extends Controller
{
    /**
     * Help Center: FAQs plus the employee's own problem reports and the
     * Report-a-Problem form. Internal accounts only (route middleware).
     */
    public function index(Request $request): Response
    {
        $reports = ProblemReport::query()
            ->where('user_id', $request->user()->id)
            ->with('ticket:id,ticket_no')
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'subject' => $r->subject,
                'category' => $r->category,
                'status' => $r->status,
                'ticket_no' => $r->ticket?->ticket_no,
                'created_at' => $r->created_at,
            ]);

        return Inertia::render('Help', [
            'reports' => $reports,
            'status' => session('status'),
            'ticket_no' => session('ticket_no'),
            'categories' => ProblemReport::CATEGORIES,
        ]);
    }

    /**
     * File a problem report. The report is received by the IT department's
     * Service Desk: an IT ticket is auto-created in the helpdesk queue
     * (same numbering + SLA as desk-logged tickets) and linked back here,
     * so the reporter can track the ticket number from Help Center.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'required|string|in:'.implode(',', ProblemReport::CATEGORIES),
            'description' => 'required|string|max:5000',
            'page_url' => 'nullable|string|max:500',
        ]);

        $isIncident = $data['category'] === 'bug';
        $prefix = $isIncident ? 'INC' : 'SRV';
        $year = now()->format('Y');
        $seq = ItTicket::where('ticket_no', 'like', "{$prefix}-{$year}-%")->count() + 1;

        $ticket = ItTicket::create([
            'ticket_no' => sprintf('%s-%s-%04d', $prefix, $year, $seq),
            'title' => '[Help Center] '.$data['subject'],
            'description' => $data['description']
                ."\n\n— Reported by {$user->name} ({$user->email}) via Help Center"
                .($data['page_url'] ? "\nPage: {$data['page_url']}" : ''),
            'category' => $isIncident ? 'incident' : 'service_request',
            'system_area' => 'erp',
            'priority' => 'P3',
            'requester_id' => $user->id,
            'requester_name' => $user->name,
            'sla_due_at' => now()->addHours(ItTicket::SLA_HOURS['P3']),
        ]);

        ProblemReport::create([
            ...$data,
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'status' => 'open',
        ]);

        return Redirect::back()->with([
            'status' => 'report-submitted',
            'ticket_no' => $ticket->ticket_no,
        ]);
    }

    /**
     * One report with everything IT sent back: the linked helpdesk ticket
     * (status, priority, handler) plus the ticket's public work-log entries.
     * Internal IT notes are never exposed. Reporters may only open their own.
     */
    public function show(Request $request, ProblemReport $report): array
    {
        abort_unless($report->user_id === $request->user()->id, 403);

        $report->loadMissing('ticket.assignee:id,name');

        $responses = [];
        if ($report->ticket) {
            $responses = $report->ticket->comments()
                ->where('is_internal', false)
                ->with('user:id,name')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'body' => $c->body,
                    'author' => $c->user?->name,
                    'created_at' => $c->created_at?->toDateTimeString(),
                ])->all();
        }

        return [
            'id' => $report->id,
            'subject' => $report->subject,
            'category' => $report->category,
            'description' => $report->description,
            'status' => $report->status,
            'page_url' => $report->page_url,
            'created_at' => $report->created_at?->toDateTimeString(),
            'ticket_no' => $report->ticket?->ticket_no,
            'ticket_status' => $report->ticket?->status,
            'ticket_priority' => $report->ticket?->priority,
            'ticket_assignee' => $report->ticket?->assignee?->name,
            'responses' => $responses,
        ];
    }

    /**
     * About this system: edition, runtime versions and the module map.
     */
    public function about(): Response
    {
        return Inertia::render('About', [
            'laravel' => Application::VERSION,
            'php' => PHP_VERSION,
            'modules' => [
                ['key' => 'HRM', 'label' => 'Human Resources'],
                ['key' => 'CRM', 'label' => 'Customer Relations'],
                ['key' => 'MAN', 'label' => 'Manufacturing'],
                ['key' => 'LOG', 'label' => 'Logistics'],
                ['key' => 'ECO', 'label' => 'E-Commerce'],
                ['key' => 'ORD', 'label' => 'Orders'],
                ['key' => 'SCM', 'label' => 'Supply Chain'],
                ['key' => 'WAR', 'label' => 'Warehouse'],
                ['key' => 'INV', 'label' => 'Inventory'],
                ['key' => 'PRO', 'label' => 'Procurement'],
                ['key' => 'FIN', 'label' => 'Finance'],
                ['key' => 'IT', 'label' => 'IT & Systems'],
                ['key' => 'WRF', 'label' => 'Workforce'],
                ['key' => 'APL', 'label' => 'Applicants'],
            ],
        ]);
    }
}
