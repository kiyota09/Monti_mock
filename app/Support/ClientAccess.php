<?php

namespace App\Support;

use App\Models\Crm\Client;
use App\Models\Crm\CrmContact;

/**
 * Resolves the effective portal identity behind a business-partner session and
 * the pages it may open.
 *
 * A session is either a representative (carrying `representative_id`, bound to
 * its CrmContact row) or the account owner (the Client row itself). The owner
 * is matched to the contact sharing its email, so page grants applied to that
 * contact also govern the owner login — otherwise the owner would keep full
 * access no matter what was granted.
 */
class ClientAccess
{
    /** The contact that represents the owner login (same account + email). */
    public static function matchingContact(?Client $client): ?CrmContact
    {
        if (! $client || ! $client->email) {
            return null;
        }

        return CrmContact::where('client_id', $client->id)
            ->where('email', $client->email)
            ->first();
    }

    /**
     * Whether the signed-in identity is the account's primary ("main account").
     *
     * @param  CrmContact|null  $rep  the representative driving the session, or
     *                                null when the account owner signed in.
     */
    public static function isPrimary(?Client $client, ?CrmContact $rep): bool
    {
        if ($rep) {
            return (bool) $rep->is_primary;
        }

        return self::ownerIsPrimary($client);
    }

    /**
     * The owner is the primary only when the contact matching its email is the
     * primary, or when no contact has been designated primary at all.
     */
    public static function ownerIsPrimary(?Client $client): bool
    {
        if (! $client) {
            return false;
        }

        $match = self::matchingContact($client);

        if ($match) {
            return (bool) $match->is_primary;
        }

        return ! CrmContact::where('client_id', $client->id)
            ->where('is_primary', true)
            ->exists();
    }

    /**
     * The page keys the session may open. `null` means unrestricted — reserved
     * for the primary and for an owner with no matching contact row.
     *
     * @return array<int,string>|null
     */
    public static function accessPages(?Client $client, ?CrmContact $rep): ?array
    {
        if ($rep) {
            return $rep->access_pages ?: [];
        }

        $match = self::matchingContact($client);

        return $match ? ($match->access_pages ?: []) : null;
    }
}
