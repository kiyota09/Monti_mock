<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Auth\Concerns\LogsOutEveryGuard;
use App\Models\Pro\Supplier;
use App\Support\NotifiesExecutive;
use App\Models\Pro\VendorRegistration;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SupplierAuthController extends Controller
{
    use LogsOutEveryGuard;

    /** Show the supplier login form */
    public function showLogin()
    {
        return Inertia::render('Auth/SupplierLogin');
    }

    /** Handle supplier login */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $this->ensureIsNotRateLimited($request);

        if (Auth::guard('supplier')->attempt($credentials, $request->boolean('remember'))) {
            $supplier = Auth::guard('supplier')->user();

            // Check if the vendor registration is approved
            $registration = VendorRegistration::where('supplier_id', $supplier->id)
                            ->where('status', 'approved')
                            ->first();

            if (!$registration) {
                // Not approved or registration missing – log them out immediately.
                // NOTE: generic message so attackers cannot distinguish bad
                // credentials from pending/rejected vendor approval.
                Auth::guard('supplier')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                RateLimiter::hit($this->throttleKey($request));

                return back()->withErrors([
                    'email' => 'Invalid credentials.',
                ])->onlyInput('email');
            }

            // Approved – let them in
            RateLimiter::clear($this->throttleKey($request));
            $request->session()->regenerate();
            return redirect()->route('supplier.dashboard');
        }

        RateLimiter::hit($this->throttleKey($request));

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip().'|supplier-login');
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

    /** Show the supplier registration form */
    public function create()
    {
        return Inertia::render('Auth/SupplierRegister');
    }

    /** Handle supplier registration */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'representative_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'email' => ['required', 'email', 'unique:suppliers,email'],
            'phone_number' => ['required', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Check if a vendor registration already exists for this email
        $existingRegistration = VendorRegistration::where('email', $validated['email'])->first();
        if ($existingRegistration) {
            return back()->withErrors([
                'email' => 'A registration with this email already exists. Please contact SCM for assistance.',
            ])->onlyInput('email');
        }

        // Use a database transaction to ensure both records are created or none
        DB::beginTransaction();

        try {
            // 1. Create the Supplier account
            $supplier = Supplier::create([
                'business_name' => $validated['business_name'],
                'representative_name' => $validated['representative_name'],
                'address' => $validated['address'],
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'password' => Hash::make($validated['password']),
            ]);

            // 2. Create the Vendor Registration ticket (status = pending)
            VendorRegistration::create([
                'supplier_id' => $supplier->id,
                'business_name' => $supplier->business_name,
                'representative_name' => $supplier->representative_name,
                'email' => $supplier->email,
                'phone_number' => $supplier->phone_number,
                'address' => $supplier->address,
                'status' => 'pending',
            ]);

              DB::commit();

              NotifiesExecutive::push('vendor', "New vendor registration: {$supplier->business_name}", "{$supplier->representative_name} ({$supplier->email}) applied and awaits approval.", 'ceo.approvals');

              return redirect()->route('supplier.login')->with('status', 'Registration successful! Please wait for SCM approval before logging in.');
        } catch (\Exception $e) {
            DB::rollBack();

            // If the supplier was created but VendorRegistration failed (e.g., duplicate email), delete the supplier
            if (isset($supplier)) {
                $supplier->delete();
            }

            return back()->withErrors([
                'email' => 'Registration failed. Please try again or contact support.',
            ])->withInput();
        }
    }

    /** Log out the supplier */
    public function logout(Request $request)
    {
        $this->logoutEveryGuard($request);

        return redirect('/');
    }
}