<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\Core\AppSetting;
use App\Models\Core\ThemePreference;
use App\Support\ThemePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AppearanceController extends Controller
{
    public function index()
    {
        $enabled = ThemePolicy::enabledThemes();

        return Inertia::render('Dashboard/IT/Manager/Appearance', [
            'enabledThemes' => $enabled,
            'themeUsage' => ThemePreference::query()
                ->selectRaw('theme, COUNT(*) as account_count')
                ->groupBy('theme')
                ->pluck('account_count', 'theme'),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'enabledThemes' => ['required', 'array', 'min:1'],
            'enabledThemes.*' => ['required', 'string', 'distinct', Rule::in(ThemePolicy::ALL)],
        ]);

        $enabled = array_values(array_intersect(ThemePolicy::ALL, $validated['enabledThemes']));
        if (! $enabled) {
            return back()->withErrors(['enabledThemes' => 'At least one color theme must remain enabled.']);
        }

        $fallback = ThemePolicy::fallbackTheme($enabled);

        DB::transaction(function () use ($enabled, $fallback) {
            AppSetting::query()->updateOrCreate(
                ['key' => AppSetting::ENABLED_COLOR_THEMES],
                ['value' => json_encode($enabled, JSON_THROW_ON_ERROR)],
            );

            ThemePreference::query()
                ->whereNotIn('theme', $enabled)
                ->update(['theme' => $fallback]);
        });

        return redirect()->route('it.appearance')
            ->with('success', 'Available color themes updated. Accounts using a disabled theme have been moved to '.$fallback.'.');
    }
}
