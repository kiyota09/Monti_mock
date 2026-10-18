<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class ClientPasswordController extends Controller
{
    /**
     * Change the password of whoever is signed in to the partner portal.
     *
     * Representatives (session carries `representative_id`) sign in with their
     * own delegated password, so they update their `crm_contacts` record; the
     * primary owner updates the business-partner account password. This stays
     * available even when the "Profile" page has been removed from a
     * representative's access, so nobody is ever locked out of changing it.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $client = Auth::guard('client')->user();
        $repId = $request->session()->get('representative_id');
        $rep = $repId ? CrmContact::find($repId) : null;

        $target = ($rep && (int) $rep->client_id === (int) $client->id) ? $rep : $client;

        if (! Hash::check($validated['current_password'], (string) $target->password)) {
            return back()->withErrors([
                'current_password' => 'Your current password is incorrect.',
            ]);
        }

        $target->password = Hash::make($validated['password']);
        $target->save();

        return back()->with('message', 'Password updated successfully.');
    }
}
