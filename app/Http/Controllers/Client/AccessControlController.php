<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmContact;
use App\Support\ClientPortal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Business-partner portal: the primary account holder grants each approved
 * representative an exact set of portal pages. This is what the
 * EnsureRepresentativeAccess middleware enforces when that representative
 * signs in.
 */
class AccessControlController extends Controller
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
                'id'           => $c->id,
                'name'         => $c->name,
                'title'        => $c->title,
                'email'        => $c->email,
                'is_primary'   => $c->is_primary,
                'status'       => $c->status,
                'has_account'  => $c->has_account,
                'access_pages' => $c->access_pages ?: [],
            ]);

        return Inertia::render('Client/AccessControl', [
            'representatives' => $representatives,
            'pages'           => collect(ClientPortal::PAGES)
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
                ->values(),
        ]);
    }

    public function update(Request $request)
    {
        $client = $this->client();

        $data = $request->validate([
            'representatives'              => 'array',
            'representatives.*.id'         => 'required|integer',
            'representatives.*.pages'      => 'array',
            'representatives.*.pages.*'    => 'string',
        ]);

        $owned = CrmContact::where('client_id', $client->id)
            ->pluck('id')
            ->all();

        foreach ($data['representatives'] ?? [] as $row) {
            if (! in_array((int) $row['id'], array_map('intval', $owned), true)) {
                continue;
            }

            $contact = CrmContact::find($row['id']);

            // The primary (account owner) is not restricted by this grid.
            if (! $contact || $contact->is_primary) {
                continue;
            }

            $contact->update([
                'access_pages' => ClientPortal::sanitizePages($row['pages'] ?? []),
            ]);
        }

        return back()->with('message', 'Access control updated.');
    }
}
