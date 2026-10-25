<?php

namespace App\Domains\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value', 'type'];

    public static function value(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("platform-setting:{$key}", function () use ($key, $default): mixed {
            $setting = static::query()->where('key', $key)->first();

            if ($setting === null) {
                return $default;
            }

            return match ($setting->type) {
                'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $setting->value,
                'json' => json_decode((string) $setting->value, true) ?? $default,
                default => $setting->value,
            };
        });
    }

    public static function put(string $key, mixed $value, string $type = 'text'): self
    {
        $stored = match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) ((int) $value),
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };

        $setting = static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type],
        );

        Cache::forever("platform-setting:{$key}", $value);

        return $setting;
    }

    public static function forget(string $key): void
    {
        static::query()->where('key', $key)->delete();
        Cache::forget("platform-setting:{$key}");
    }
}
