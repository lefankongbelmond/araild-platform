<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** Réglages du réseau modifiables depuis le back-office (mode allégé, tarifs…). */
class SocialSetting extends Model
{
    protected $connection = 'central';
    protected $table = 'social_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public static function all_(): array
    {
        return Cache::remember('social:settings', 60, function () {
            try {
                return Schema::connection('central')->hasTable('social_settings')
                    ? static::query()->pluck('value', 'key')->all() : [];
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all_()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        Cache::forget('social:settings');
    }
}
