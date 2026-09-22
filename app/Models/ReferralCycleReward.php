<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralCycleReward extends Model
{
    use HasFactory;

    // ============================================================
    //  Constants — الحالات
    // ============================================================
    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    // ============================================================
    //  Configuration
    // ============================================================
    protected $table = 'referral_cycle_rewards';

    protected $fillable = [
        'cycle_id',
        'referrer_id',
        'total_burned_l1',
        'total_burned_l2',
        'reward_l1',
        'reward_l2',
        'total_reward',
        'currency',
        'percent_l1',
        'percent_l2',
        'referred_count_l1',
        'referred_count_l2',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'total_burned_l1'   => 'decimal:2',
        'total_burned_l2'   => 'decimal:2',
        'reward_l1'         => 'decimal:2',
        'reward_l2'         => 'decimal:2',
        'total_reward'      => 'decimal:2',
        'percent_l1'        => 'decimal:2',
        'percent_l2'        => 'decimal:2',
        'referred_count_l1' => 'integer',
        'referred_count_l2' => 'integer',
        'paid_at'           => 'datetime',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ReferralCycle::class, 'cycle_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeForReferrer($query, int $userId)
    {
        return $query->where('referrer_id', $userId);
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

    public function getTotalBurnedAttribute(): float
    {
        return (float) $this->total_burned_l1 + (float) $this->total_burned_l2;
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => '🟡 معلّق',
            self::STATUS_PAID      => '🟢 مدفوع',
            self::STATUS_CANCELLED => '🔴 ملغى',
            default                => '❔',
        };
    }
}
