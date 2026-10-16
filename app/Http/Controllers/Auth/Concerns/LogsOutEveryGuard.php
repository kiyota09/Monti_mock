<?php

namespace App\Http\Controllers\Auth\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait LogsOutEveryGuard
{
    /**
     * Log out of every session guard (web, client, supplier, applicant),
     * then destroy the session.
     *
     * Logging out a single guard leaves the other guards' remember-me
     * cookies alive, so the very next request silently re-authenticates via
     * recaller and the user appears to still be logged in — the "log out
     * twice" bug. Logging every guard out clears each recaller cookie and
     * cycles each remember token, so one logout is final.
     */
    protected function logoutEveryGuard(Request $request): void
    {
        foreach (['web', 'client', 'supplier', 'applicant'] as $guard) {
            Auth::guard($guard)->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
