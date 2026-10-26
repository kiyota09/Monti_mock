<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmContact;
use App\Rules\PortalEmailAvailable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Business-partner portal: the primary account holder registers the delegated
 * officers ("representatives") of their own company. Each registration lands in
 * the CRM account's Contacts tab as `pending`, where CRM decides whether to
 * grant it a portal login.
 */
class RepresentativeController extends Controller
{
    protected function client()
    {
        return Auth::guard('client')->user();
    }

    public function index()
    {
        $client = $this->client();

        $representatives = CrmContact::where('client_id', $client->id)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get()
            ->map(fn (CrmContact $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'title' => $c->title,
                'email' => $c->email,
                'phone' => $c->phone,
                'notes' => $c->notes,
                'is_primary' => $c->is_primary,
                'is_decision_maker' => $c->is_decision_maker,
                'status' => $c->status,
                'has_account' => $c->has_account,
                'origin' => $c->origin,
                'access_pages' => $c->access_pages ?: [],
                'created_at' => $c->created_at,
            ]);

        return Inertia::render('Client/Representatives', [
            'representatives' => $representatives,
        ]);
    }

    public function store(Request $request)
    {
        $client = $this->client();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'email' => ['nullable', 'email', 'max:255', new PortalEmailAvailable],
            'phone' => ['nullable', 'string', 'regex:/^\+63\d{10}$/'],
            'notes' => 'nullable|string|max:2000',
            'is_decision_maker' => 'nullable|boolean',
        ], [
            'phone.regex' => 'The phone number must be +63 followed by exactly 10 digits.',
        ]);

        CrmContact::create([
            'client_id' => $client->id,
            'name' => $data['name'],
            'title' => $data['title'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_decision_maker' => (bool) ($data['is_decision_maker'] ?? false),
            'is_primary' => false,
            'status' => 'pending',
            'has_account' => false,
            'origin' => 'partner',
        ]);

        return back()->with('message', 'Representative submitted for approval.');
    }

    public function destroy(CrmContact $contact)
    {
        $client = $this->client();

        abort_unless((int) $contact->client_id === (int) $client->id, 403);
        abort_if($contact->is_primary, 403, 'The primary contact cannot be removed.');

        $contact->delete();

        return back()->with('message', 'Representative removed.');
    }
}
