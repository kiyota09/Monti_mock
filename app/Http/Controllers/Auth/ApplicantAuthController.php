<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ApplicantOtpMail;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\ApplicantStatusHistory;
use App\Support\NotifiesApplicant;
use App\Support\TerminatedAccess;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Applicant portal auth (Auth-1 style OTP flow).
 *
 * Registration takes email + password only (same structure as the
 * Auth-1 Register page), issues a 6-digit email OTP, and only creates
 * the portal session after the code is verified on Auth/VerifyOtp.
 * Legacy rows filed via the public /apply form claim their account by
 * registering with the same email; the full profile is completed later
 * in the applicant portal (Profile page).
 */
class ApplicantAuthController extends Controller
{
    private const OTP_TTL_MINUTES = 10;
    private const OTP_MAX_ATTEMPTS = 5;
    private const RESEND_COOLDOWN_SECONDS = 60;
    private const REGISTER_MAX_ATTEMPTS = 5;
    private const REGISTER_DECAY_SECONDS = 600;

    /** Progressive OTP lockout durations (minutes) by lockout cycle. */
    private const OTP_LOCKOUT_MINUTES = [5, 10, 30, 60];

    public function create()
    {
        return Inertia::render('Auth/ApplicantRegister');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|string|lowercase|email|max:255',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $this->ensureRegisterIsNotRateLimited($request);
        RateLimiter::hit($this->registerThrottleKey($request), self::REGISTER_DECAY_SECONDS);

        // Terminated employees (company or personal email) may never
        // (re-)register on the applicant portal.
        if (TerminatedAccess::isApplicantBlockedByEmail($request->email)) {
            throw ValidationException::withMessages([
                'email' => TerminatedAccess::APPLICANT_BLOCK_MESSAGE,
            ]);
        }

        $existing = Applicant::where('email', $request->email)->first();

        if ($existing && ($existing->status === TerminatedAccess::TERMINATED_STATUS)) {
            throw ValidationException::withMessages([
                'email' => TerminatedAccess::APPLICANT_BLOCK_MESSAGE,
            ]);
        }

        if ($existing && $existing->password && $existing->email_verified_at) {
            throw ValidationException::withMessages([
                'email' => 'This email is already registered and verified. Please log in instead.',
            ]);
        }

        if ($existing) {
            // Claim flow (legacy /apply row) or re-registration of an
            // unverified account: set the password, keep existing profile.
            $existing->update([
                'password' => $request->password, // hashed via cast
            ]);
            $applicant = $existing->fresh();
        } else {
            // Minimal row (Auth-1 structure): profile details are filled in
            // later via the applicant portal Profile page.
            $applicant = Applicant::create([
                'first_name' => 'New',
                'last_name' => 'Applicant',
                'email' => $request->email,
                'phone_number' => '-',
                'password' => $request->password, // hashed via cast
                'street_address' => '-',
                'city' => '-',
                'state_province' => '-',
                'postal_zip_code' => '-',
                'position_applied' => 'General Application',
                'status' => 'Pending',
                'archived' => false,
            ]);
        }

        $this->issueOtp($applicant);

        try {
            Mail::to($applicant->email)->send(
                new ApplicantOtpMail($this->plainOtpCache($applicant), self::OTP_TTL_MINUTES)
            );
        } catch (\Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'email' => 'Registration saved but the verification email could not be sent. Please try resending the code.',
            ]);
        }

        RateLimiter::clear($this->registerThrottleKey($request));

        return redirect()
            ->route('applicant.verify-otp.show', ['email' => $applicant->email])
            ->with('info', 'Registration successful! Check your email for the verification code.');
    }

    public function showVerifyForm(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        if (TerminatedAccess::isApplicantBlockedByEmail($request->email)) {
            return redirect()->route('applicant.login')
                ->withErrors(['email' => TerminatedAccess::APPLICANT_BLOCK_MESSAGE]);
        }

        $applicant = Applicant::where('email', $request->email)->firstOrFail();

        if ($applicant->status === TerminatedAccess::TERMINATED_STATUS) {
            return redirect()->route('applicant.login')
                ->withErrors(['email' => TerminatedAccess::APPLICANT_BLOCK_MESSAGE]);
        }

        if ($applicant->email_verified_at) {
            return redirect()->route('applicant.login')
                ->with('status', 'Email already verified. Please log in.');
        }

        return Inertia::render('Auth/VerifyOtp', $this->verifyProps($applicant));
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        $applicant = Applicant::where('email', $request->email)->first();

        if (! $applicant || ! $applicant->email_otp) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid verification code. Please try again.',
            ]);
        }

        // Never complete verification for a terminated employee email.
        if ($applicant->status === TerminatedAccess::TERMINATED_STATUS
            || TerminatedAccess::isApplicantBlockedByEmail($applicant->email)) {
            throw ValidationException::withMessages([
                'otp' => TerminatedAccess::APPLICANT_BLOCK_MESSAGE,
            ]);
        }

        if ($this->otpLockedSeconds($applicant) > 0) {
            throw ValidationException::withMessages([
                'otp' => $this->lockoutMessage($applicant),
            ]);
        }

        if ($applicant->email_otp_expires_at && $applicant->email_otp_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => 'This code has expired. Please request a new code.',
            ]);
        }

        if (! Hash::check($request->otp, $applicant->email_otp)) {
            $applicant->increment('email_otp_attempts');

            if ($applicant->email_otp_attempts >= self::OTP_MAX_ATTEMPTS) {
                $this->applyOtpLockout($applicant);

                throw ValidationException::withMessages([
                    'otp' => $this->lockoutMessage($applicant),
                ]);
            }

            $remaining = self::OTP_MAX_ATTEMPTS - (int) $applicant->email_otp_attempts;

            throw ValidationException::withMessages([
                'otp' => "Invalid verification code. {$remaining} attempt(s) remaining.",
            ]);
        }

        $applicant->update([
            'email_verified_at' => now(),
            'email_otp' => null,
            'email_otp_expires_at' => null,
            'email_otp_attempts' => 0,
            'email_otp_locked_until' => null,
            'status' => 'Submitted',
        ]);

        ApplicantStatusHistory::create([
            'applicant_id' => $applicant->id, 'from_status' => 'Pending',
            'to_status' => 'Submitted', 'changed_by' => null,
            'reason' => 'Email verified via OTP',
        ]);
        NotifiesApplicant::welcome($applicant);

        Auth::guard('applicant')->login($applicant);
        $request->session()->regenerate();

        return $this->portalRedirect($request);
    }

    public function resendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $applicant = Applicant::where('email', $request->email)->first();

        if (! $applicant) {
            throw ValidationException::withMessages([
                'email' => 'Failed to resend code. Please try again.',
            ]);
        }

        if ($applicant->status === TerminatedAccess::TERMINATED_STATUS
            || TerminatedAccess::isApplicantBlockedByEmail($applicant->email)) {
            throw ValidationException::withMessages([
                'email' => TerminatedAccess::APPLICANT_BLOCK_MESSAGE,
            ]);
        }

        if ($applicant->email_verified_at) {
            throw ValidationException::withMessages([
                'email' => 'This email is already registered and verified. Please log in instead.',
            ]);
        }

        if ($this->otpLockedSeconds($applicant) > 0) {
            throw ValidationException::withMessages([
                'email' => $this->lockoutMessage($applicant),
            ]);
        }

        if ($applicant->email_otp_sent_at) {
            $wait = self::RESEND_COOLDOWN_SECONDS - $applicant->email_otp_sent_at->diffInSeconds(now());
            if ($wait > 0) {
                throw ValidationException::withMessages([
                    'email' => "Please wait {$wait} seconds before requesting another code.",
                ]);
            }
        }

        $this->issueOtp($applicant);

        try {
            Mail::to($applicant->email)->send(
                new ApplicantOtpMail($this->plainOtpCache($applicant), self::OTP_TTL_MINUTES)
            );
        } catch (\Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'email' => 'Failed to resend code. Please try again.',
            ]);
        }

        return back()->with('info', 'A new verification code was sent to your email.');
    }

    public function showLogin()
    {
        return Inertia::render('Auth/ApplicantLogin');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $this->ensureIsNotRateLimited($request);

        $applicant = Applicant::where('email', $credentials['email'])->first();

        // Diagnostic login: each failure returns a specific message (and is
        // logged) so the exact reason is visible instead of a generic
        // "Invalid credentials."
        if (! $applicant) {
            RateLimiter::hit($this->throttleKey($request));
            Log::warning('Applicant login failed: email not found', ['email' => $credentials['email']]);

            return back()->withErrors(['email' => 'No applicant account found with this email. Please register first.']);
        }

        if (! $applicant->password) {
            RateLimiter::hit($this->throttleKey($request));
            Log::warning('Applicant login failed: no portal password set', ['applicant_id' => $applicant->id]);

            return back()->withErrors(['email' => 'No portal password set for this email yet — please register to claim your application.']);
        }

        if (! Hash::check($credentials['password'], $applicant->password)) {
            RateLimiter::hit($this->throttleKey($request));
            Log::warning('Applicant login failed: wrong password', ['applicant_id' => $applicant->id]);

            return back()->withErrors(['password' => 'Incorrect password for this email address.']);
        }

        // Password correct from here on.
        // Terminated employees are permanently refused — both the
        // system-generated company email and the personal email.
        if ($applicant->status === TerminatedAccess::TERMINATED_STATUS
            || TerminatedAccess::isApplicantBlockedByEmail($applicant->email)) {
            RateLimiter::hit($this->throttleKey($request));
            Log::warning('Applicant login blocked: terminated employee email', [
                'applicant_id' => $applicant->id, 'status' => $applicant->status,
            ]);

            return back()->withErrors(['email' => TerminatedAccess::APPLICANT_BLOCK_MESSAGE]);
        }

        if ((bool) $applicant->archived) {
            RateLimiter::hit($this->throttleKey($request));
            Log::warning('Applicant login blocked: account archived', [
                'applicant_id' => $applicant->id, 'status' => $applicant->status,
            ]);

            $reason = match ($applicant->status) {
                'Deactivated' => 'This account was deactivated. Please contact support to reactivate it.',
                'Rejected' => 'This application was rejected, so the portal account is disabled.',
                default => 'This account is archived and disabled. Please contact support.',
            };

            return back()->withErrors(['email' => $reason]);
        }

        // Unverified accounts must complete OTP verification first.
        if (! $applicant->email_verified_at) {
            RateLimiter::clear($this->throttleKey($request));
            Log::info('Applicant login redirected: email not verified', ['applicant_id' => $applicant->id]);

            if ($this->otpLockedSeconds($applicant) <= 0 && ! $this->hasValidOtp($applicant)) {
                $this->issueOtp($applicant);
                try {
                    Mail::to($applicant->email)->send(
                        new ApplicantOtpMail($this->plainOtpCache($applicant), self::OTP_TTL_MINUTES)
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return redirect()
                ->route('applicant.verify-otp.show', ['email' => $applicant->email])
                ->with('info', 'Please verify your email address to continue.');
        }

        RateLimiter::clear($this->throttleKey($request));

        Auth::guard('applicant')->login($applicant, $request->boolean('remember'));
        $request->session()->regenerate();

        return $this->portalRedirect($request);
    }

    // ---------------------------------------------------------------
    // OTP helpers
    // ---------------------------------------------------------------

    /**
     * Issue (or re-issue) a 6-digit OTP. The plain code is kept only for
     * the current request so it can be mailed; only the hash is stored.
     */
    protected function issueOtp(Applicant $applicant): void
    {
        $code = (string) random_int(100000, 999999);

        $applicant->update([
            'email_otp' => Hash::make($code),
            'email_otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'email_otp_attempts' => 0,
            'email_otp_sent_at' => now(),
        ]);

        // Plain code lives only in memory for this request (mailed below).
        $applicant->setAttribute('_plain_otp', $code);
    }

    protected function plainOtpCache(Applicant $applicant): string
    {
        return (string) $applicant->getAttribute('_plain_otp');
    }

    protected function hasValidOtp(Applicant $applicant): bool
    {
        return $applicant->email_otp
            && $applicant->email_otp_expires_at
            && $applicant->email_otp_expires_at->isFuture();
    }

    protected function otpLockedSeconds(Applicant $applicant): int
    {
        if (! $applicant->email_otp_locked_until) {
            return 0;
        }

        $seconds = now()->diffInSeconds($applicant->email_otp_locked_until, false);

        return $seconds > 0 ? (int) $seconds : 0;
    }

    protected function lockoutMinutesForCycle(int $cycle): int
    {
        $index = max(1, $cycle) - 1;

        return self::OTP_LOCKOUT_MINUTES[min($index, count(self::OTP_LOCKOUT_MINUTES) - 1)];
    }

    protected function applyOtpLockout(Applicant $applicant): void
    {
        $cycle = (int) $applicant->email_otp_lock_count + 1;
        $minutes = $this->lockoutMinutesForCycle($cycle);

        $applicant->update([
            'email_otp_attempts' => 0,
            'email_otp_lock_count' => $cycle,
            'email_otp_locked_until' => now()->addMinutes($minutes),
        ]);

        $applicant->refresh();
    }

    /**
     * Message format the VerifyOtp page parses: "Lock #N" + "Nm".
     */
    protected function lockoutMessage(Applicant $applicant): string
    {
        $cycle = max(1, (int) $applicant->email_otp_lock_count);
        $minutes = $this->lockoutMinutesForCycle($cycle);

        return "Too many failed attempts. Lock #{$cycle} — account is temporarily locked for {$minutes}m. Please wait before trying again.";
    }

    protected function verifyProps(Applicant $applicant): array
    {
        $lockedSeconds = $this->otpLockedSeconds($applicant);
        $cycle = (int) $applicant->email_otp_lock_count;

        return [
            'email' => $applicant->email,
            'lockout_seconds' => $lockedSeconds,
            'is_locked' => $lockedSeconds > 0,
            'otp_attempts' => (int) $applicant->email_otp_attempts,
            'max_attempts' => self::OTP_MAX_ATTEMPTS,
            'lockout_cycle' => $cycle,
            'lockout_minutes' => $cycle > 0 ? $this->lockoutMinutesForCycle($cycle) : self::OTP_LOCKOUT_MINUTES[0],
        ];
    }

    protected function registerThrottleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip().'|applicant-register');
    }

    protected function ensureRegisterIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->registerThrottleKey($request), self::REGISTER_MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->registerThrottleKey($request));

        throw ValidationException::withMessages([
            'email' => "Account is temporarily locked. Please try again in {$seconds} seconds.",
        ])->errorBag('default');
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip().'|applicant-login');
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
        Auth::guard('applicant')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('applicant.login');
    }

    /**
     * Multi-guard safe landing: only honor a stored intended URL when it
     * points inside the applicant portal. A stale intended URL from an
     * employee/client bounce would otherwise drop a fresh applicant
     * session at the wrong (employee) login screen.
     */
    protected function portalRedirect(Request $request)
    {
        $intended = $request->session()->pull('url.intended', route('applicant.dashboard'));
        $path = parse_url((string) $intended, PHP_URL_PATH) ?: '/';

        if (str_starts_with($path, '/applicant')) {
            return redirect()->to($intended);
        }

        return redirect()->route('applicant.dashboard');
    }
}
