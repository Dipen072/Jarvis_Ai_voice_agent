<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JarvisSetting extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get a setting value for a user by key, with optional default.
     */
    public static function getValue(int $userId, string $key, mixed $default = null): mixed
    {
        $setting = static::where('user_id', $userId)->where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set (create or update) a setting for a user.
     */
    public static function setValue(int $userId, string $key, mixed $value): static
    {
        return static::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value'   => $value]
        );
    }

    /**
     * Get all settings for a user as a key → value array.
     */
    public static function getAllForUser(int $userId): array
    {
        return static::where('user_id', $userId)
            ->pluck('value', 'key')
            ->toArray();
    }
}
