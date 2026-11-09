<?php

namespace App\Http\Middleware;

use App\Models\Core\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Closure;

class EnsureApplicantIsNotEmployee
{
    public function handle(Request $request, Closure $next)
    {
        $applicant = Auth::guard('applicant')->user();
        if ($applicant && ($applicant->hired_user_id || User::whereRaw('LOWER(personal_email) = ?', [strtolower(trim((string) $applicant->email))])->exists())) {
            Auth::guard('applicant')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'identity' => 'This personal email is now linked to an employee account. Use the employee portal to sign in.',
            ]);
        }

        return $next($request);
    }
}
