<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    private const CACHE_PREFIX = 'setting:';

    private const CACHE_TTL = 3600;

    public static function get(string $key, ?string $default = null): ?string
    {
        return Cache::remember(self::CACHE_PREFIX.$key, self::CACHE_TTL, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            return $setting?->value ?? $default;
        });
    }

    public static function set(string $key, ?string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_PREFIX.$key);
    }

    public static function getMany(array $keys): array
    {
        $result = [];
        foreach ($keys as $key => $default) {
            if (is_int($key)) {
                $result[$default] = self::get($default);
            } else {
                $result[$key] = self::get($key, $default);
            }
        }

        return $result;
    }
}
