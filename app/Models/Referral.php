<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasFactory;

    // ============================================================
    //  Constants — الأنواع
    // ============================================================
    public const TYPE_INSTANT = 'instant';
    public const TYPE_CYCLE   = 'cycle';

    // ============================================================
    //  Constants — المستويات
    // ============================================================
    public const LEVEL_1 = 1;
    public const LEVEL_2 = 2;

    // ============================================================
    //  Constants — الحالات
    // ============================================================
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_BLOCKED  = 'blocked';

    // ============================================================
    //  Configuration
    // ============================================================
    protected $table = 'referrals';

    protected $fillable = [
        'referrer_id',
        'referred_id',
        'level',
        'type',
        'status',
        'total_deposited',
        'total_withdrawn',
        'total_burned',
        'total_earned',
        'deposits_count',
        'withdrawals_count',
    ];

    protected $casts = [
        'level'             => 'integer',
        'total_deposited'   => 'decimal:2',
        'total_withdrawn'   => 'decimal:2',
        'total_burned'      => 'decimal:2',
        'total_earned'      => 'decimal:2',
        'deposits_count'    => 'integer',
        'withdrawals_count' => 'integer',
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

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeLevel1($query)
    {
        return $query->where('level', self::LEVEL_1);
    }

    public function scopeLevel2($query)
    {
        return $query->where('level', self::LEVEL_2);
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

    public function scopeForReferred($query, int $userId)
    {
        return $query->where('referred_id', $userId);
    }

    // ============================================================
    //  Helpers — Updates
    // ============================================================

    /**
     * إضافة إيداع (يُحدّث الإحصائيات + الحرق).
     */
    public function addDeposit(float $amount, float $earned = 0): void
    {
        $this->increment('total_deposited', $amount);
        $this->increment('deposits_count');

        if ($earned > 0) {
            $this->increment('total_earned', $earned);
        }

        $this->recalculateBurn();
        $this->refresh();
    }

    /**
     * إضافة سحب.
     */
    public function addWithdrawal(float $amount): void
    {
        $this->increment('total_withdrawn', $amount);
        $this->increment('withdrawals_count');

        $this->recalculateBurn();
        $this->refresh();
    }

    /**
     * إعادة حساب الحرق = إيداع - سحب.
     */
    public function recalculateBurn(): void
    {
        $burn = max(
            0,
            (float) $this->total_deposited - (float) $this->total_withdrawn
        );

        $this->update(['total_burned' => $burn]);
    }

    /**
     * إضافة مكافأة.
     */
    public function addEarning(float $amount): void
    {
        $this->increment('total_earned', $amount);
        $this->refresh();
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getLevelLabelAttribute(): string
    {
        return match ($this->level) {
            self::LEVEL_1 => '🥇 المستوى 1',
            self::LEVEL_2 => '🥈 المستوى 2',
            default       => '❔',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_INSTANT => '⚡ فوري',
            self::TYPE_CYCLE   => '📅 دوري',
            default            => '❔',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE   => '🟢 نشط',
            self::STATUS_INACTIVE => '🟡 معطّل',
            self::STATUS_BLOCKED  => '🔴 محظور',
            default               => '❔',
        };
    }

    public function getNetBalanceAttribute(): float
    {
        return (float) $this->total_deposited - (float) $this->total_withdrawn;
    }
}
