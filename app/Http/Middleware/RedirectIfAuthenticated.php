<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // ✅ Redirect B2B Clients to their portal
                if ($guard === 'client') {
                    return redirect()->route('client.dashboard');
                }

                // ✅ Redirect Applicants to their portal
                if ($guard === 'applicant') {
                    return redirect()->route('applicant.dashboard');
                }

                // ✅ Redirect Employees to the internal ERP dashboard
                // Adjust this to your actual employee dashboard route name
                return redirect()->route('dashboard');
            }
        }

        return $next($request);
    }
}
