<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

/**
 * Forced password change for agency logins (must_change_password flag).
 */
class PasswordController extends Controller
{
    public function edit()
    {
        return Inertia::render('Agency/Password/Edit');
    }

    public function update(Request $request)
    {
        $user = $request->user('agency');

        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        if (! $user->password || ! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->update(['password' => $data['password'], 'must_change_password' => false]);

        return redirect()->route('agency.dashboard')->with('success', 'Password updated.');
    }
}
