<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    public const TYPE_STRING  = 'string';
    public const TYPE_INT     = 'int';
    public const TYPE_BOOL    = 'bool';
    public const TYPE_JSON    = 'json';
    public const TYPE_DECIMAL = 'decimal';

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'is_editable',
    ];

    protected $casts = [
        'is_editable' => 'boolean',
    ];

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopeEditable($query)
    {
        return $query->where('is_editable', true);
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getTypedValueAttribute(): mixed
    {
        return match ($this->type) {
            self::TYPE_INT     => (int) $this->value,
            self::TYPE_BOOL    => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_JSON    => json_decode($this->value ?? '[]', true),
            self::TYPE_DECIMAL => (float) $this->value,
            default            => $this->value,
        };
    }

    public function getDisplayValueAttribute(): string
    {
        $value = $this->typed_value;

        if ($this->type === self::TYPE_BOOL) {
            return $value ? '🟢' : '🔴';
        }

        if (str_contains($this->key, 'percent')) {
            return $value . '%';
        }

        if (
            str_contains($this->key, 'amount') ||
            str_contains($this->key, 'price') ||
            str_contains($this->key, 'limit')
        ) {
            return number_format((float) $value);
        }

        return (string) $value;
    }

    // ============================================================
    //  Static Helpers
    // ============================================================

    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting.{$key}";

        $value = Cache::rememberForever($cacheKey, function () use ($key) {
            $setting = static::where('key', $key)->first();
            return $setting?->typed_value;
        });

        return $value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $setting = static::where('key', $key)->first();

        if (! $setting) {
            return;
        }

        $stored = match ($setting->type) {
            self::TYPE_JSON => json_encode($value, JSON_UNESCAPED_UNICODE),
            self::TYPE_BOOL => $value ? '1' : '0',
            default         => (string) $value,
        };

        $setting->update(['value' => $stored]);

        Cache::forget("setting.{$key}");
    }

    public static function flush(): void
    {
        static::all()->each(function ($setting) {
            Cache::forget("setting.{$setting->key}");
        });
    }

    public static function byGroup(string $group)
    {
        return static::where('group', $group)->orderBy('id')->get();
    }
}
