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
 * login is always unrestricted: contact-level page grants only apply to
 * delegated representatives, never to the company account that owns them.
 */
class ClientAccess
{
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

        return $client !== null;
    }

    /**
     * The page keys the session may open. `null` means unrestricted — the
     * account-owner login is always unrestricted; representatives use their
     * explicitly granted pages.
     *
     * @return array<int,string>|null
     */
    public static function accessPages(?Client $client, ?CrmContact $rep): ?array
    {
        if ($rep) {
            return $rep->access_pages ?: [];
        }

        return null;
    }
}
