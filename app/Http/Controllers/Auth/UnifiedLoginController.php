<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Crm\Client;
use App\Models\Crm\CrmContact;
use App\Models\Hrm\AgencyUser;
use App\Models\Hrm\Applicant;
use App\Models\Pro\Supplier;
use App\Http\Middleware\GeofenceAccessMiddleware;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UnifiedLoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
            'status' => session('status') ?? session('message') ?? session('info'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identity' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $identity = mb_strtolower(trim($data['identity']));
        $key = Str::transliterate($identity.'|'.$request->ip().'|unified-login');
        if (RateLimiter::tooManyAttempts($key, 5)) {
            event(new Lockout($request));
            throw ValidationException::withMessages([
                'identity' => trans('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($key),
                    'minutes' => ceil(RateLimiter::availableIn($key) / 60),
                ]),
            ]);
        }

        $matches = $this->matchingAccounts($identity, $data['password']);
        if (count($matches) !== 1) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages([
                'identity' => count($matches) > 1
                    ? 'More than one portal account matches these credentials. Use a distinct email/password for each account or contact support.'
                    : 'The email or employee ID and password do not match an active portal account.',
            ]);
        }

        RateLimiter::clear($key);
        $match = $matches[0];

        // Keep each portal's existing account checks, session handling and
        // role-aware destination after detecting which account owns the login.
        $request->merge([
            'email' => $identity,
            'employee_id' => $identity,
        ]);

        return match ($match['portal']) {
            'client' => app(ClientAuthController::class)->login($request),
            'supplier' => app(SupplierAuthController::class)->login($request),
            'applicant' => app(ApplicantAuthController::class)->login($request),
            'agency' => app(\App\Http\Controllers\Agency\AgencyAuthController::class)->login($request),
            'employee' => $this->loginEmployee($request, $match['account']),
        };
    }

    /** @return array<int, array{portal: string, account: mixed}> */
    private function matchingAccounts(string $identity, string $password): array
    {
        $accounts = [];
        $isEmail = str_contains($identity, '@');

        $employees = User::query()
            ->where(function ($query) use ($identity, $isEmail) {
                if ($isEmail) {
                    $query->whereRaw('LOWER(email) = ?', [$identity]);
                } else {
                    $query->whereRaw('LOWER(employee_id) = ?', [$identity]);
                }
            })->limit(3)->get();
        foreach ($employees as $employee) {
            if ($employee->password && Hash::check($password, $employee->password)) {
                $accounts[] = ['portal' => 'employee', 'account' => $employee];
            }
        }

        if (! $isEmail) {
            return $accounts;
        }

        $client = Client::query()->whereRaw('LOWER(email) = ?', [$identity])->first();
        if ($client && $client->password && Hash::check($password, $client->password)) {
            $accounts[] = ['portal' => 'client', 'account' => $client];
        } elseif (! $client) {
            $representatives = CrmContact::query()
                ->whereRaw('LOWER(email) = ?', [$identity])
                ->where('has_account', true)
                ->where('status', 'active')
                ->whereNotNull('password')
                ->whereNotNull('client_id')
                ->limit(2)->get();
            if ($representatives->count() === 1
                && Hash::check($password, $representatives->first()->password)) {
                $accounts[] = ['portal' => 'client', 'account' => $representatives->first()];
            }
        }

        foreach ([
            'supplier' => Supplier::class,
            'applicant' => Applicant::class,
            'agency' => AgencyUser::class,
        ] as $portal => $model) {
            $candidates = $model::query()->whereRaw('LOWER(email) = ?', [$identity])->limit(3)->get();
            foreach ($candidates as $account) {
                if (! $account->password || ! Hash::check($password, $account->password)) {
                    continue;
                }

                // Hiring creates an employee portal account but retains the
                // applicant record for HR history. When that record points to
                // the employee account that already matched these credentials,
                // resolve this login to the employee portal instead of treating
                // the linked historical applicant row as a second account.
                if ($portal === 'applicant' && $account->hired_user_id) {
                    $isMatchingHiredEmployee = collect($accounts)->contains(fn ($match) =>
                        $match['portal'] === 'employee'
                        && (int) $match['account']->id === (int) $account->hired_user_id);
                    if ($isMatchingHiredEmployee) {
                        continue;
                    }
                }

                $accounts[] = ['portal' => $portal, 'account' => $account];
            }
        }

        return $accounts;
    }

    private function loginEmployee(Request $request, User $employee): RedirectResponse
    {
        if (isset($employee->is_active) && ! $employee->is_active) {
            throw ValidationException::withMessages([
                'identity' => 'This account has been deactivated. Please contact HR.',
            ]);
        }

        if ($employee->position === 'trainee') {
            throw ValidationException::withMessages([
                'identity' => 'Trainee accounts are not permitted to use this portal until promoted.',
            ]);
        }

        // IT accounts must be able to sign in from any location so they can
        // maintain the company geofence and troubleshoot access issues.
        if (strtoupper((string) $employee->role) !== 'IT'
            && ! GeofenceAccessMiddleware::allows($request)) {
            throw ValidationException::withMessages([
                'identity' => 'Access denied: this employee account can only sign in from an authorized work location.',
            ]);
        }

        Auth::guard('web')->login($employee, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
