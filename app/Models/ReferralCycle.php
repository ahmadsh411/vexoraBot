<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralCycle extends Model
{
    use HasFactory;

    // ============================================================
    //  Constants — الحالات
    // ============================================================
    public const STATUS_OPEN       = 'open';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_CLOSED     = 'closed';
    public const STATUS_CANCELLED  = 'cancelled';

    // ============================================================
    //  Configuration
    // ============================================================
    protected $table = 'referral_cycles';

    protected $fillable = [
        'start_date',
        'end_date',
        'status',
        'total_burned',
        'total_rewards',
        'referrers_count',
        'referred_count',
        'processed_at',
    ];

    protected $casts = [
        'start_date'      => 'date',
        'end_date'        => 'date',
        'total_burned'    => 'decimal:2',
        'total_rewards'   => 'decimal:2',
        'referrers_count' => 'integer',
        'referred_count'  => 'integer',
        'processed_at'    => 'datetime',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function rewards(): HasMany
    {
        return $this->hasMany(ReferralCycleReward::class, 'cycle_id');
    }

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('start_date');
    }

    // ============================================================
    //  Helpers — Status
    // ============================================================

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function hasEnded(): bool
    {
        return $this->end_date->isPast();
    }

    public function daysRemaining(): int
    {
        if ($this->hasEnded()) {
            return 0;
        }

        return (int) now()->diffInDays($this->end_date);
    }

    // ============================================================
    //  Helpers — Status Changes
    // ============================================================

    public function markProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    public function markClosed(): void
    {
        $this->update([
            'status'       => self::STATUS_CLOSED,
            'processed_at' => now(),
        ]);
    }

    public function markCancelled(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN       => '🟢 جارية',
            self::STATUS_PROCESSING => '🟡 قيد المعالجة',
            self::STATUS_CLOSED     => '⚫ مغلقة',
            self::STATUS_CANCELLED  => '🔴 ملغاة',
            default                 => '❔',
        };
    }

    public function getDurationLabelAttribute(): string
    {
        return $this->start_date->format('Y-m-d')
            . ' → '
            . $this->end_date->format('Y-m-d');
    }
}
