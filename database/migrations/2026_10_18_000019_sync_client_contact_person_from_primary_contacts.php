<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill: mirror each account's flagged primary contact onto the client
     * record so the representative shown in the client portal (and everywhere
     * else reading clients.contact_person) matches the CRM Accounts contacts.
     */
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('crm_contacts')) {
            return;
        }

        $primaries = DB::table('crm_contacts')
            ->whereNotNull('client_id')
            ->where('is_primary', true)
            ->orderBy('id')
            ->get(['client_id', 'name']);

        foreach ($primaries as $contact) {
            if (empty($contact->name)) {
                continue;
            }

            DB::table('clients')
                ->where('id', $contact->client_id)
                ->where(function ($query) use ($contact) {
                    $query->whereNull('contact_person')
                        ->orWhere('contact_person', '!=', $contact->name);
                })
                ->update(['contact_person' => $contact->name]);
        }
    }

    public function down(): void
    {
        // Data-only sync — intentionally not reversible.
    }
};
