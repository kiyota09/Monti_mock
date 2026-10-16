<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Client;
use App\Models\Crm\CrmClientAssignment;
use App\Models\Crm\CrmMeeting;
use App\Models\Crm\CrmFeedback;
use App\Support\RouteHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Traits\HasPagePermissions;

class CustomerProfileController extends Controller
{
    use HasPagePermissions;

    /**
     * Display a list of clients (all for managers/CEO, assigned only for staff).
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['CRM', 'CEO', 'COO'], true)) {
            abort(403, 'Unauthorized access.');
        }

        if ($user->role === 'CRM' && $user->position === 'staff') {
            // Staff sees only assigned clients
            $assignments = CrmClientAssignment::with('client.logo')->where('staff_id', $user->id)->get();
            $clients = $assignments->pluck('client');
            if ($clients->isEmpty()) {
                return Inertia::render('Dashboard/CRM/CustomerProfilesList', [
                    'clients' => [],
                    'message' => 'No clients assigned to you.'
                ]);
            }
        } else {
            // Manager/CEO: optionally filter by assigned clients if ?assigned=1
            if ($request->query('assigned') == 1) {
                // Get clients that have at least one assignment (any staff)
                $assignedClientIds = CrmClientAssignment::distinct()->pluck('client_id');
                $clients = Client::with('logo')->whereIn('id', $assignedClientIds)
                    ->where('status', 'active')
                    ->get();
            } else {
                // Show all active clients
                $clients = Client::with('logo')->where('status', 'active')->get();
            }
        }

        $permissions = $this->getPagePermissionsForModule('CRM');

        return Inertia::render('Dashboard/CRM/CustomerProfilesList', [
            'clients' => $clients,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Account 360: company record + contacts, open deals, order history,
     * activity timeline, cases, meetings/feedback and credit standing.
     * The route key is hashed (see App\Support\RouteHash).
     */
    public function show($key)
    {
        $user = Auth::user();
        $client = Client::with(['feedback.assignee', 'logo'])
            ->findOrFail(RouteHash::decodeOrFail((string) $key, 'client'));

        // Restrict staff to only their assigned clients
        if ($user->role === 'CRM' && $user->position === 'staff') {
            $isAssigned = CrmClientAssignment::where('client_id', $client->id)
                                              ->where('staff_id', $user->id)
                                              ->exists();
            if (!$isAssigned) {
                abort(403, 'You are not assigned to this client.');
            }
        } elseif (!in_array($user->role, ['CRM', 'CEO', 'COO'], true)) {
            abort(403, 'Unauthorized access.');
        }

        $client->load([
            'contacts', 'creditAccount',
            'opportunities.owner', 'opportunities.lead',
            'activities.owner', 'cases.owner',
            'purchaseOrders' => fn ($q) => $q->latest()->take(10),
        ]);

        $meetings = CrmMeeting::where('client_id', $client->id)->latest()->get();
        $feedback = CrmFeedback::where('client_id', $client->id)->with('assignee')->latest()->get();

        $openValue = $client->opportunities
            ->reject(fn ($o) => in_array($o->stage, ['won', 'lost'], true))
            ->sum(fn ($o) => (float) $o->value);

        $permissions = $this->getPagePermissionsForModule('CRM');

        return Inertia::render('Dashboard/CRM/CustomerProfile', [
            'client'   => $client,
            'meetings' => $meetings,
            'feedback' => $feedback,
            'openPipeline' => round($openValue, 2),
            'permissions' => $permissions,
        ]);
    }
}