<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    /** When enabled, the driver portal shows a "Simulate delivery now" test control. */
    public const LOGISTICS_DELIVERY_SIMULATION = 'logistics.delivery_simulation';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value === null ? $default : (string) $value;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::getValue($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public static function setBool(string $key, bool $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value ? '1' : '0']);
    }
}
