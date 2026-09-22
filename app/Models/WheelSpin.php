<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WheelSpin extends Model
{
    use HasFactory;

    // ============================================================
    //  Constants — المصادر
    // ============================================================
    public const SOURCE_DEPOSIT_SYP = 'deposit_syp';
    public const SOURCE_DEPOSIT_USD = 'deposit_usd';
    public const SOURCE_REFERRAL    = 'referral';
    public const SOURCE_ADMIN       = 'admin';
    public const SOURCE_MANUAL      = 'manual';

    // ============================================================
    //  Configuration
    // ============================================================
    protected $fillable = [
        'user_id',
        'wheel_id',
        'prize_id',
        'won_value',
        'currency',
        'source',
        'metadata',
    ];

    protected $casts = [
        'won_value' => 'decimal:2',
        'metadata'  => 'array',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wheel(): BelongsTo
    {
        return $this->belongsTo(Wheel::class);
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(WheelPrize::class, 'prize_id');
    }

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeFromDeposit($query)
    {
        return $query->whereIn('source', [
            self::SOURCE_DEPOSIT_SYP,
            self::SOURCE_DEPOSIT_USD,
        ]);
    }

    public function scopeFromReferral($query)
    {
        return $query->where('source', self::SOURCE_REFERRAL);
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getSourceLabelAttribute(): string
    {
        return match ($this->source) {
            self::SOURCE_DEPOSIT_SYP => '📥 إيداع SYP',
            self::SOURCE_DEPOSIT_USD => '💵 إيداع USD',
            self::SOURCE_REFERRAL    => '🎯 إحالة',
            self::SOURCE_ADMIN       => '👑 إداري',
            self::SOURCE_MANUAL      => '✋ يدوي',
            default                  => '❔',
        };
    }
}
