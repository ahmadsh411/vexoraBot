<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralReward extends Model
{
    use HasFactory;

    // ============================================================
    //  Constants — الأنواع
    // ============================================================
    public const TYPE_INSTANT = 'instant';
    public const TYPE_CYCLE   = 'cycle';

    // ============================================================
    //  Constants — الأساس
    // ============================================================
    public const BASIS_DEPOSIT = 'deposit';
    public const BASIS_BURN    = 'burn';

    // ============================================================
    //  Constants — الحالات
    // ============================================================
    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    // ============================================================
    //  Configuration
    // ============================================================
    protected $table = 'referral_rewards';

    protected $fillable = [
        'referrer_id',
        'referred_id',
        'transaction_id',
        'level',
        'type',
        'basis',
        'amount',
        'currency',
        'commission_percent',
        'status',
        'notes',
        'metadata',
        'paid_at',
    ];

    protected $casts = [
        'level'              => 'integer',
        'amount'             => 'decimal:2',
        'commission_percent' => 'decimal:2',
        'metadata'           => 'array',
        'paid_at'            => 'datetime',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInstant($query)
    {
        return $query->where('type', self::TYPE_INSTANT);
    }

    public function scopeCycle($query)
    {
        return $query->where('type', self::TYPE_CYCLE);
    }

    public function scopeForReferrer($query, int $userId)
    {
        return $query->where('referrer_id', $userId);
    }

    public function scopeLevel1($query)
    {
        return $query->where('level', Referral::LEVEL_1);
    }

    public function scopeLevel2($query)
    {
        return $query->where('level', Referral::LEVEL_2);
    }

    // ============================================================
    //  Helpers
    // ============================================================

    public function markPaid(): void
    {
        $this->update([
            'status'  => self::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    public function markCancelled(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'notes'  => $reason,
        ]);
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_INSTANT => '⚡ فوري',
            self::TYPE_CYCLE   => '📅 دوري',
            default            => '❔',
        };
    }

    public function getBasisLabelAttribute(): string
    {
        return match ($this->basis) {
            self::BASIS_DEPOSIT => '📥 إيداع',
            self::BASIS_BURN    => '🔥 حرق',
            default             => '❔',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => '🟡 معلّق',
            self::STATUS_PAID      => '🟢 مدفوع',
            self::STATUS_CANCELLED => '🔴 ملغى',
            default                => '❔',
        };
    }

    public function getLevelLabelAttribute(): string
    {
        return match ($this->level) {
            Referral::LEVEL_1 => '🥇 L1',
            Referral::LEVEL_2 => '🥈 L2',
            default           => '❔',
        };
    }
}
