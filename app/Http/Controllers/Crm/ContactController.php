<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Client;
use App\Models\Crm\CrmClientAssignment;
use App\Models\Crm\CrmContact;
use App\Models\Crm\CrmLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ContactController extends Controller
{
    protected function account($id): Client
    {
        $user = Auth::user();
        $client = Client::findOrFail($id);

        if ($user->role === 'CRM' && $user->position === 'staff') {
            abort_unless(
                CrmClientAssignment::where('client_id', $client->id)->where('staff_id', $user->id)->exists(),
                403, 'You are not assigned to this client.'
            );
        } elseif (! in_array($user->role, ['CRM', 'CEO', 'COO'], true)) {
            abort(403, 'Unauthorized access.');
        }

        return $client;
    }

    protected function prospect(CrmLead $lead): CrmLead
    {
        abort_unless(in_array(Auth::user()->role, ['CRM', 'CEO', 'COO'], true), 403, 'Unauthorized access.');

        return $lead;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'lead_id' => 'nullable|exists:crm_leads,id',
            'name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:32',
            'is_decision_maker' => 'nullable|boolean',
            'is_primary' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);
        abort_if(empty($data['client_id']) && empty($data['lead_id']), 422, 'Contact must belong to an account or a lead.');

        if (! empty($data['client_id'])) {
            $this->account($data['client_id']);
        } else {
            $this->prospect(CrmLead::findOrFail($data['lead_id']));
        }

        if (! empty($data['is_primary'])) {
            $this->clearPrimary($data);
        }

        CrmContact::create([
            ...$data,
            'is_decision_maker' => (bool) ($data['is_decision_maker'] ?? false),
            'is_primary' => (bool) ($data['is_primary'] ?? false),
        ]);

        return back()->with('message', 'Contact added.');
    }

    public function update(Request $request, CrmContact $contact)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:32',
            'is_decision_maker' => 'nullable|boolean',
            'is_primary' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($contact->client_id) {
            $this->account($contact->client_id);
        } else {
            $this->prospect($contact->lead);
        }

        if (! empty($data['is_primary'])) {
            $this->clearPrimary($contact->only('client_id', 'lead_id'));
        }

        $contact->update([
            ...$data,
            'is_decision_maker' => (bool) ($data['is_decision_maker'] ?? false),
            'is_primary' => (bool) ($data['is_primary'] ?? false),
        ]);

        return back()->with('message', 'Contact updated.');
    }

    public function destroy(CrmContact $contact)
    {
        if ($contact->client_id) {
            $this->account($contact->client_id);
        } else {
            $this->prospect($contact->lead);
        }

        $contact->delete();

        return back()->with('message', 'Contact removed.');
    }

    public function makePrimary(CrmContact $contact)
    {
        if ($contact->client_id) {
            $this->account($contact->client_id);
        } else {
            $this->prospect($contact->lead);
        }

        $this->clearPrimary($contact->only('client_id', 'lead_id'));
        $contact->update(['is_primary' => true]);

        return back()->with('message', 'Primary contact set.');
    }

    protected function clearPrimary(array $scope): void
    {
        $q = CrmContact::where('is_primary', true);
        if (! empty($scope['client_id'])) {
            $q->where('client_id', $scope['client_id']);
        } else {
            $q->where('lead_id', $scope['lead_id']);
        }
        $q->update(['is_primary' => false]);
    }
}
