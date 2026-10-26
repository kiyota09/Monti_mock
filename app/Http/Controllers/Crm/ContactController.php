<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Mail\RepresentativeWelcomeMail;
use App\Models\Crm\Client;
use App\Models\Crm\CrmClientAssignment;
use App\Models\Crm\CrmContact;
use App\Models\Crm\CrmLead;
use App\Rules\PortalEmailAvailable;
use App\Support\ClientPortal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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

        $this->syncPrimaryContactToClient($data['client_id'] ?? null);

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

        if (($contact->has_account || $contact->password) && ! empty($data['email'])) {
            $newEmail = mb_strtolower(trim($data['email']));

            if ($newEmail !== mb_strtolower(trim((string) $contact->email))) {
                $validator = Validator::make(
                    ['email' => $data['email']],
                    ['email' => ['required', 'email', new PortalEmailAvailable($contact->id)]],
                );

                if ($validator->fails()) {
                    return back()->with('error', $validator->errors()->first('email'));
                }
            }
        }

        if (! empty($data['is_primary'])) {
            $this->clearPrimary($contact->only('client_id', 'lead_id'));
        }

        $contact->update([
            ...$data,
            'is_decision_maker' => (bool) ($data['is_decision_maker'] ?? false),
            'is_primary' => (bool) ($data['is_primary'] ?? false),
        ]);

        $this->syncPrimaryContactToClient($contact->client_id);

        return back()->with('message', 'Contact updated.');
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

        $this->syncPrimaryContactToClient($contact->client_id);

        return back()->with('message', 'Primary contact set.');
    }

    /**
     * Approve a partner-registered representative: create their portal login
     * with a system-generated temporary password and a full default page grant.
     */
    public function createAccount(CrmContact $contact)
    {
        abort_unless($contact->client_id, 422, 'Representative must belong to an account.');

        $this->account($contact->client_id);

        $email = mb_strtolower(trim((string) $contact->email));

        if ($email === '') {
            return back()->with('error', 'Add an email address to this contact before creating a portal account.');
        }

        $conflict = Client::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->exists()
            || CrmContact::whereRaw('LOWER(email) = ?', [$email])
                ->whereKeyNot($contact->id)
                ->where(fn ($query) => $query->where('has_account', true)->orWhereNotNull('password'))
                ->exists();

        if ($conflict) {
            return back()->with('error', 'That email is already used by another portal account. Update the contact email first.');
        }

        $temporary = Str::password(10, true, true, false, false);

        $contact->update([
            'status' => 'active',
            'has_account' => true,
            'password' => Hash::make($temporary),
            'account_created_at' => now(),
            'access_pages' => $contact->access_pages ?: ClientPortal::keys(),
        ]);

        $client = Client::find($contact->client_id);

        // Email the representative their sign-in details — best-effort so a mail
        // hiccup never blocks the account from being created.
        if ($contact->email && $client) {
            try {
                Mail::to($contact->email)->send(new RepresentativeWelcomeMail(
                    representativeName: $contact->name,
                    companyName: $client->company_name,
                    email: $contact->email,
                    password: $temporary,
                    portalUrl: route('client.login'),
                ));

                return back()->with('message', 'Account created for '.$contact->name.'. Sign-in details emailed to '.$contact->email.'.');
            } catch (\Throwable $e) {
                Log::warning('Representative welcome email failed for contact #'.$contact->id.': '.$e->getMessage());
            }
        }

        return back()->with('message', 'Account created for '.$contact->name.'. Temporary password: '.$temporary);
    }

    /** Reject a partner-registered representative (no login granted). */
    public function rejectAccount(CrmContact $contact)
    {
        if ($contact->client_id) {
            $this->account($contact->client_id);
        }

        $contact->update([
            'status' => 'rejected',
            'has_account' => false,
            'password' => null,
            'access_pages' => null,
        ]);

        return back()->with('message', $contact->name."'s registration was rejected.");
    }

    /**
     * Keep the client record's representative ("contact_person") in lock-step
     * with the account's primary contact so the Accounts page, the ECO client
     * directory and the client portal Profile all show the same person.
     */
    protected function syncPrimaryContactToClient(?int $clientId): void
    {
        if (empty($clientId)) {
            return;
        }

        $primary = CrmContact::where('client_id', $clientId)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        // Only mirror an unambiguous representative: the marked primary
        // contact, or the lone contact when none is flagged.
        $contact = $primary->firstWhere('is_primary', true)
            ?? ($primary->count() === 1 ? $primary->first() : null);

        if (! $contact || ! $contact->name) {
            return;
        }

        $client = Client::find($clientId);
        if ($client && $client->contact_person !== $contact->name) {
            $client->update(['contact_person' => $contact->name]);
        }
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
