<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class Setting extends Model
{
    protected $fillable = ['key', 'group', 'value', 'type', 'label',
                           'description', 'is_locked', 'updated_by'];

    protected function casts(): array
    {
        return ['is_locked' => 'boolean'];
    }

    /**
     * Read a setting, cast to its declared type.
     *   Setting::get('security.max_login_attempts')  -> 5 (int)
     *
     * Only the value and type are cached — never the model itself. A
     * cached Eloquent model has to be serialized and rebuilt, which
     * fails on the database cache driver.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = Cache::rememberForever("setting.$key", function () use ($key) {
            $found = static::query()->where('key', $key)->first();

            return $found
                ? ['value' => $found->value, 'type' => $found->type]
                : null;
        });

        if (! is_array($row)) {
            return $default;
        }

        return match ($row['type']) {
            'int'     => (int) $row['value'],
            'decimal' => (float) $row['value'],
            'bool'    => filter_var($row['value'], FILTER_VALIDATE_BOOLEAN),
            'json'    => json_decode($row['value'], true),
            default   => $row['value'],
        };
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->where('key', $key)->update([
            'value'      => is_array($value) ? json_encode($value) : (string) $value,
            'updated_by' => Auth::id(),
        ]);

        Cache::forget("setting.$key");
    }
}