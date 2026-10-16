<?php

namespace App\Http\Controllers\Eco;

use App\Http\Controllers\Controller;
use App\Models\Crm\Client;
use Inertia\Inertia;

class EcoClientController extends Controller
{
    /**
     * Display-only directory of all MontiTextile clients.
     * No chat/messaging actions — information viewing only.
     */
    public function index()
    {
        $clients = Client::with('logo')->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'company_name' => $c->company_name,
                'business_type' => $c->business_type,
                'contact_person' => $c->contact_person,
                'email' => $c->email,
                'phone' => $c->phone,
                'company_address' => $c->company_address,
                'city' => $c->city,
                'province' => $c->province,
                'postal_code' => $c->postal_code,
                'status' => $c->status,
                'credit_limit' => $c->credit_limit,
                'payment_terms_days' => $c->payment_terms_days,
                'created_at' => $c->created_at,
                'logo_url' => $c->logo?->logo_url,
            ]);

        return Inertia::render('Dashboard/ECO/ClientList', [
            'clients' => $clients,
        ]);
    }
}
