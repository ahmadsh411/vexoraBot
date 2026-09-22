<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wheel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'deposit_syp_threshold',
        'min_deposit_syp_threshold',
        'max_deposit_syp_threshold',
        'deposit_usd_threshold',
        'min_deposit_usd_threshold',
        'max_deposit_usd_threshold',
        'referral_threshold',
        'min_referral_threshold',
        'max_referral_threshold',
        'daily_limit',
        'min_daily_limit',
        'max_daily_limit',
        'max_stored_spins',
        'auto_grant_on_deposit',
    ];

    protected $casts = [
        'is_active'                  => 'boolean',
        'auto_grant_on_deposit'      => 'boolean',
        'deposit_syp_threshold'      => 'decimal:2',
        'min_deposit_syp_threshold'  => 'decimal:2',
        'max_deposit_syp_threshold'  => 'decimal:2',
        'deposit_usd_threshold'      => 'decimal:2',
        'min_deposit_usd_threshold'  => 'decimal:2',
        'max_deposit_usd_threshold'  => 'decimal:2',
        'referral_threshold'         => 'integer',
        'min_referral_threshold'     => 'integer',
        'max_referral_threshold'     => 'integer',
        'daily_limit'                => 'integer',
        'min_daily_limit'            => 'integer',
        'max_daily_limit'            => 'integer',
        'max_stored_spins'           => 'integer',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function prizes(): HasMany
    {
        return $this->hasMany(WheelPrize::class)->orderBy('sort_order');
    }

    public function activePrizes(): HasMany
    {
        return $this->hasMany(WheelPrize::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function spins(): HasMany
    {
        return $this->hasMany(WheelSpin::class);
    }

    public function userStates(): HasMany
    {
        return $this->hasMany(WheelUserState::class);
    }

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ============================================================
    //  Helpers — Prizes
    // ============================================================

    public function getTotalWeight(): int
    {
        return (int) $this->prizes()->sum('weight');
    }

    public function isWeightValid(): bool
    {
        return $this->getTotalWeight() === 100;
    }

    // ============================================================
    //  Helpers — Spins Calculation
    // ============================================================

    public function calculateSpinsFromDeposit(float $amount, string $currency): int
    {
        $currency = strtoupper($currency);

        $threshold = match ($currency) {
            'SYP' => (float) $this->deposit_syp_threshold,
            'USD' => (float) $this->deposit_usd_threshold,
            default => 0,
        };

        if ($threshold <= 0) {
            return 0;
        }

        return (int) floor($amount / $threshold);
    }

    public function calculateSpinsFromReferrals(int $referralsCount): int
    {
        if ($this->referral_threshold <= 0) {
            return 0;
        }

        return (int) floor($referralsCount / $this->referral_threshold);
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? '🟢 مفعّلة' : '🔴 معطّلة';
    }
}
