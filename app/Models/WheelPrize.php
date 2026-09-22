<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WheelPrize extends Model
{
    use HasFactory;

    // ============================================================
    //  Constants — الأنواع
    // ============================================================
    public const TYPE_BALANCE = 'balance';
    public const TYPE_EMPTY   = 'empty';
    public const TYPE_RECYCLE = 'recycle';

    // ============================================================
    //  Configuration
    // ============================================================
    protected $fillable = [
        'wheel_id',
        'name',
        'icon',
        'color',
        'value',
        'currency',
        'type',
        'weight',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'value'     => 'decimal:2',
        'weight'    => 'integer',
        'is_active' => 'boolean',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function wheel(): BelongsTo
    {
        return $this->belongsTo(Wheel::class);
    }

    public function spins(): HasMany
    {
        return $this->hasMany(WheelSpin::class, 'prize_id');
    }

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBalance($query)
    {
        return $query->where('type', self::TYPE_BALANCE);
    }

    public function scopeEmpty($query)
    {
        return $query->where('type', self::TYPE_EMPTY);
    }

    public function scopeRecycle($query)
    {
        return $query->where('type', self::TYPE_RECYCLE);
    }

    // ============================================================
    //  Helpers — Type
    // ============================================================

    public function isBalance(): bool
    {
        return $this->type === self::TYPE_BALANCE;
    }

    public function isEmpty(): bool
    {
        return $this->type === self::TYPE_EMPTY;
    }

    public function isRecycle(): bool
    {
        return $this->type === self::TYPE_RECYCLE;
    }

    public function givesReward(): bool
    {
        return $this->isBalance() && (float) $this->value > 0;
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_BALANCE => '💰 رصيد',
            self::TYPE_EMPTY   => '😢 فارغة',
            self::TYPE_RECYCLE => '♻️ إعادة تدوير',
            default            => '❔',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? '🟢' : '🔴';
    }
}
