<?php

namespace App\Http\Controllers;

use App\Models\Core\ThemePreference;
use App\Support\ThemePolicy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ThemePreferenceController extends Controller
{
    public function update(Request $request)
    {
        $identity = ThemePolicy::currentIdentity();
        abort_unless($identity, 401, 'Sign in to save a theme preference.');

        $data = $request->validate([
            'theme' => ['required', 'string', Rule::in(ThemePolicy::ALL)],
        ]);
        $enabled = ThemePolicy::enabledThemes();
        if (! in_array($data['theme'], $enabled, true)) {
            throw ValidationException::withMessages([
                'theme' => 'That color theme is disabled. Choose one of the currently available themes.',
            ]);
        }

        [$guard, $user] = $identity;
        ThemePreference::query()->updateOrCreate(
            ['guard' => $guard, 'user_id' => $user->getAuthIdentifier()],
            ['theme' => $data['theme']],
        );

        return response()->json(['theme' => $data['theme']]);
    }
}
