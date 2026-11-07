<?php

namespace App\Support;

use App\Models\Core\AppSetting;
use App\Models\Core\ThemePreference;
use Illuminate\Support\Facades\Auth;

class ThemePolicy
{
    public const ALL = ['light', 'dark', 'blue', 'red', 'green'];

    public static function enabledThemes(): array
    {
        $stored = json_decode((string) AppSetting::getValue(AppSetting::ENABLED_COLOR_THEMES, ''), true);
        if (! is_array($stored)) return self::ALL;

        $enabled = array_values(array_intersect(self::ALL, $stored));

        // A corrupt or empty setting must never leave the system without a theme.
        return $enabled ?: self::ALL;
    }

    public static function fallbackTheme(?array $enabled = null): string
    {
        $enabled ??= self::enabledThemes();

        return in_array('light', $enabled, true) ? 'light' : ($enabled[0] ?? 'light');
    }

    /** @return array{0: string, 1: mixed}|null */
    public static function currentIdentity(): ?array
    {
        foreach (['web', 'client', 'supplier', 'applicant', 'agency'] as $guard) {
            try {
                $user = Auth::guard($guard)->user();
            } catch (\InvalidArgumentException) {
                continue;
            }

            if ($user) return [$guard, $user];
        }

        return null;
    }

    public static function forRequest(): array
    {
        $enabled = self::enabledThemes();
        $identity = self::currentIdentity();
        $preference = null;
        if ($identity) {
            [$guard, $user] = $identity;
            $preference = ThemePreference::query()
                ->where('guard', $guard)
                ->where('user_id', $user->getAuthIdentifier())
                ->value('theme');
            if (! in_array($preference, $enabled, true)) $preference = self::fallbackTheme($enabled);
        }

        return [
            'enabledThemes' => $enabled,
            'preference' => $preference,
            'preferenceUrl' => $identity ? '/theme-preference' : null,
        ];
    }
}
