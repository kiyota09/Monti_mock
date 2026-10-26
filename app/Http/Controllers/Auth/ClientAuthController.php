<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\LogsOutEveryGuard;
use App\Http\Controllers\Controller;
use App\Models\Crm\Client;
use App\Models\Crm\CrmContact;
use App\Models\Crm\CrmLogoPartner;
use App\Rules\PortalEmailAvailable;
use App\Support\ClientPortal;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
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
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255', 'unique:clients',
                new PortalEmailAvailable,
            ],
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

        $email = mb_strtolower(trim($credentials['email']));
        $client = Client::whereRaw('LOWER(email) = ?', [$email])->first();

        // An email owned by a business client always authenticates as that
        // client. It must never fall through to a representative login with the
        // same address, otherwise a duplicate email could open another
        // company's portal.
        if ($client) {
            if (! Hash::check($credentials['password'], $client->password)
                || ! in_array($client->status, ['approved', 'active'], true)) {
                RateLimiter::hit($this->throttleKey($request));

                return back()->withErrors([
                    'email' => 'Invalid credentials.',
                ]);
            }

            RateLimiter::clear($this->throttleKey($request));

            Auth::guard('client')->login($client, $request->boolean('remember'));
            $request->session()->forget('representative_id');
            $request->session()->regenerate();

            // The account owner always lands on the dashboard with full access.
            return redirect()->intended(route('client.dashboard'));
        }

        // Not the account owner? Try to sign in as an approved representative.
        // Representatives authenticate against the owner account so all the
        // data-scoped portal controllers keep working unchanged; the session
        // flag + access middleware restrict what they can reach.
        $representatives = CrmContact::whereRaw('LOWER(email) = ?', [$email])
            ->where('has_account', true)
            ->where('status', 'active')
            ->whereNotNull('password')
            ->whereNotNull('client_id')
            ->limit(2)
            ->get();

        // A duplicate representative login is ambiguous, so refuse it instead
        // of picking an arbitrary company account.
        if ($representatives->count() > 1) {
            RateLimiter::hit($this->throttleKey($request));

            return back()->withErrors([
                'email' => 'Invalid credentials.',
            ]);
        }

        $representative = $representatives->first();

        if ($representative && Hash::check($credentials['password'], $representative->password)) {
            $owner = Client::find($representative->client_id);

            if ($owner && in_array($owner->status, ['approved', 'active'], true)) {
                RateLimiter::clear($this->throttleKey($request));

                Auth::guard('client')->login($owner, $request->boolean('remember'));
                $request->session()->put('representative_id', $representative->id);
                $request->session()->regenerate();

                $home = ClientPortal::firstRoute($representative->access_pages ?: []);

                return redirect()->intended($home ? route($home) : route('client.dashboard'));
            }
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
