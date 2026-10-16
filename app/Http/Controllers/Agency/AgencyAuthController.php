<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Agency;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AgencyAuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Auth/AgencyLogin');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $this->ensureIsNotRateLimited($request);

        if (Auth::guard('agency')->attempt($credentials, $request->boolean('remember'))) {
            /** @var \App\Models\Hrm\AgencyUser $agencyUser */
            $agencyUser = Auth::guard('agency')->user();
            $agencyUser->loadMissing('agency');

            // Suspended/terminated agencies lose portal access, as do
            // individually deactivated logins. Records are always kept.
            $blocked = ! $agencyUser->is_active
                || ! $agencyUser->agency
                || $agencyUser->agency->status !== Agency::STATUS_ACTIVE;

            if ($blocked) {
                Auth::guard('agency')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                RateLimiter::hit($this->throttleKey($request));

                return back()->withErrors([
                    'email' => 'This agency login is no longer active. Please contact Monti Corp HR.',
                ])->onlyInput('email');
            }

            RateLimiter::clear($this->throttleKey($request));
            $request->session()->regenerate();

            // First login (or fresh temp password) forces a password change.
            if ($agencyUser->must_change_password) {
                return redirect()->route('agency.password.edit');
            }

            return redirect()->route('agency.dashboard');
        }

        RateLimiter::hit($this->throttleKey($request));

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::guard('agency')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('agency.login');
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip().'|agency-login');
    }

    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw \Illuminate\Validation\ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }
}
