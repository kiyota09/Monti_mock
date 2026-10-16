<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Auth\Concerns\LogsOutEveryGuard;
use App\Models\Crm\Client;
use App\Models\Crm\CrmLogoPartner;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ClientAuthController extends Controller
{
    use LogsOutEveryGuard;
    public function create()
    {
        return Inertia::render('Auth/ClientRegister');
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'business_type' => 'required|string|max:100',
            'contact_person' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:clients',
            'phone' => 'required|string|max:20',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'company_address' => 'required|string|max:1000',
            'tin_number' => 'nullable|string|max:50',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp,svg|max:5120',
        ]);

        $client = Client::create([
            'company_name' => $request->company_name,
            'business_type' => $request->business_type,
            'tin_number' => $request->tin_number,
            'contact_person' => $request->contact_person,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'company_address' => $request->company_address,
            'status' => 'pending', // default pending
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('crm-logos', 'public');
            CrmLogoPartner::create([
                'client_id' => $client->id,
                'logo_path' => $path,
                'original_name' => $request->file('logo')->getClientOriginalName(),
            ]);
        }

        return redirect()->route('client.login')->with('message', 'Registration submitted. Please wait for admin approval.');
    }

    public function showLogin()
    {
        return Inertia::render('Auth/ClientLogin');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $this->ensureIsNotRateLimited($request);

        $client = Client::where('email', $credentials['email'])->first();

        if ($client && Hash::check($credentials['password'], $client->password)) {
            // Allow both 'approved' and 'active' as valid statuses.
            // NOTE: a single generic message is used for every failure so
            // attackers cannot enumerate registered emails or approval state.
            if (! in_array($client->status, ['approved', 'active'])) {
                RateLimiter::hit($this->throttleKey($request));

                return back()->withErrors([
                    'email' => 'Invalid credentials.',
                ]);
            }

            RateLimiter::clear($this->throttleKey($request));

            Auth::guard('client')->login($client, $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()->intended(route('client.dashboard'));
        }

        RateLimiter::hit($this->throttleKey($request));

        return back()->withErrors([
            'email' => 'Invalid credentials.',
        ]);
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip().'|client-login');
    }

    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function logout(Request $request)
    {
        $this->logoutEveryGuard($request);

        return redirect()->route('client.login');
    }
}
