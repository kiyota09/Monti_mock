<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CanAccessScm
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        // CEO, secretary, general manager always have access
        if (in_array($user->position, ['secretary', 'special_officer'])) {
            return $next($request);
        }

        // Check if user has explicit SCM access permission
        $hasAccess = \App\Models\Scm\ScmAccessPermission::where('user_id', $user->id)
                        ->where('can_access_scm', true)
                        ->exists();

        if (!$hasAccess) {
            abort(403, 'You do not have access to the SCM module.');
        }

        return $next($request);
    }
}