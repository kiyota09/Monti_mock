<?php

namespace App\Http\Middleware;

use App\Models\Crm\CrmContact;
use App\Support\ClientAccess;
use App\Support\ClientPortal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the page-level access granted to delegated business-partner
 * representatives.
 *
 * Every partner login authenticates against the `client` guard as the owning
 * business-partner account (so data-scoped portal controllers keep working).
 * A representative session additionally carries `representative_id`; the owner
 * session does not. The owner login is therefore always unrestricted, while a
 * representative:
 *   - cannot open Representative / Access Control pages unless primary;
 *   - may only open pages in that contact's access_pages.
 */
class EnsureRepresentativeAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = Auth::guard('client')->user();
        $repId = $request->session()->get('representative_id');
        $rep = null;

        if ($repId) {
            $rep = CrmContact::find($repId);

            // Account revoked/disabled under their feet → end the session.
            if (! $rep || ! $rep->has_account || $rep->status !== 'active') {
                Auth::guard('client')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('client.login');
            }
        }

        $isPrimary = ClientAccess::isPrimary($client, $rep);
        $name = $request->route()?->getName();

        // Primary-only management pages.
        if ($name && (str_starts_with($name, 'client.representatives')
            || str_starts_with($name, 'client.access-control'))) {
            if (! $isPrimary) {
                abort(403, 'Only the primary contact can manage representatives.');
            }

            return $next($request);
        }

        // The primary is unrestricted across the whole portal.
        if ($isPrimary) {
            return $next($request);
        }

        $page = ClientPortal::pageForRoute($name);

        // Routes with no page mapping (logout, etc.) stay reachable.
        if ($page === null) {
            return $next($request);
        }

        $allowed = ClientAccess::accessPages($client, $rep);

        // null = unrestricted (an owner with no matching contact row).
        if ($allowed === null) {
            return $next($request);
        }

        if (! in_array($page, $allowed, true)) {
            abort(403, 'Your account administrator has not granted access to this page.');
        }

        return $next($request);
    }
}
