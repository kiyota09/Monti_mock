<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeLoginController extends Controller
{
    /**
     * Display the employee login view.
     */
    public function create(): Response
    {
        // This renders the Login.vue page you updated with the toggle
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'employee_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited($request);

        // 1. Attempt to authenticate the user using the employee_id and password
        if (Auth::attempt(['employee_id' => $credentials['employee_id'], 'password' => $credentials['password']], $request->boolean('remember'))) {

            $user = Auth::user();

            // 2. Reject deactivated accounts (is_active is otherwise never checked)
            if (isset($user->is_active) && ! $user->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                RateLimiter::hit($this->throttleKey($request));

                throw ValidationException::withMessages([
                    'employee_id' => __('This account has been deactivated. Please contact HR.'),
                ]);
            }

            // 3. Check if the user's position is 'trainee' based on the database enum
            if ($user->position === 'trainee') {
                // Log them out immediately to prevent session creation
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // Throw an error back to the Employee ID field in Login.vue
                throw ValidationException::withMessages([
                    'employee_id' => __('Access Denied. Trainee accounts are not permitted to use this portal until promoted.'),
                ]);
            }

            RateLimiter::clear($this->throttleKey($request));

            // 4. If they are 'staff' or 'manager', regenerate session and redirect
            $request->session()->regenerate();

            // Redirect to the Dashboard/USERS/app.vue route defined in your web.php
            return redirect()->intended(route('employee.ui.dashboard'));
        }

        RateLimiter::hit($this->throttleKey($request));

        // Standard error for incorrect credentials
        return back()->withErrors([
            'employee_id' => __('The provided employee ID does not match our records.'),
        ]);
    }

    /**
     * Throttle employee-ID login attempts (5 per minute per ID+IP),
     * mirroring LoginRequest behaviour.
     */
    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('employee_id')).'|'.$request->ip());
    }

    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'employee_id' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }
}
