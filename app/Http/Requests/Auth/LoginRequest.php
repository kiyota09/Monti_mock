<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        // Post-authentication account-state checks. These run after a
        // successful credential check so Oracle-disabled / trainee accounts
        // can never obtain a session through this portal (previously only
        // the employee-ID portal blocked trainees and nothing checked
        // is_active at all).
        $user = Auth::user();

        if ($user && isset($user->is_active) && ! $user->is_active) {
            Auth::logout();
            $this->session()->invalidate();
            $this->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('This account has been deactivated. Please contact HR.'),
            ]);
        }

        if ($user && ($user->position ?? null) === 'trainee') {
            Auth::logout();
            $this->session()->invalidate();
            $this->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('Access Denied. Trainee accounts must use the trainee portal until promoted.'),
            ]);
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        $email = strtolower(trim($this->string('email')));
        return Str::transliterate($email.'|'.$this->ip());
    }
}
