<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

/**
 * Forced password change for accounts carrying a system-issued password
 * (temporary / initial / recovered). Unlike the regular change-password
 * endpoints, this does not require the current password — the user just
 * authenticated with it — and it clears the `must_change_password` flag.
 */
class ForcedPasswordController extends Controller
{
    public function employee(Request $request): RedirectResponse
    {
        return $this->apply($request, 'web');
    }

    public function client(Request $request): RedirectResponse
    {
        return $this->apply($request, 'client');
    }

    public function applicant(Request $request): RedirectResponse
    {
        return $this->apply($request, 'applicant');
    }

    public function supplier(Request $request): RedirectResponse
    {
        return $this->apply($request, 'supplier');
    }

    public function agency(Request $request): RedirectResponse
    {
        return $this->apply($request, 'agency');
    }

    private function apply(Request $request, string $guard): RedirectResponse
    {
        $user = Auth::guard($guard)->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        return back()->with('message', 'Your password has been updated.');
    }
}
