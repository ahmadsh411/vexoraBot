<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralSetting extends Model
{
    use HasFactory;

    protected $table = 'referral_settings';

    protected $fillable = [
        'instant_level_1_percent',
        'instant_level_2_percent',
        'cycle_level_1_percent',
        'cycle_level_2_percent',
        'cycle_days',
        'min_instant_reward',
        'max_instant_reward',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'instant_level_1_percent' => 'decimal:2',
        'instant_level_2_percent' => 'decimal:2',
        'cycle_level_1_percent'   => 'decimal:2',
        'cycle_level_2_percent'   => 'decimal:2',
        'cycle_days'              => 'integer',
        'min_instant_reward'      => 'decimal:2',
        'max_instant_reward'      => 'decimal:2',
        'is_active'               => 'boolean',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ============================================================
    //  Static Helpers
    // ============================================================

    /**
     * الإعدادات الحالية (Singleton).
     */
    public static function current(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'instant_level_1_percent' => 5.00,
                'instant_level_2_percent' => 2.00,
                'cycle_level_1_percent'   => 10.00,
                'cycle_level_2_percent'   => 3.00,
                'cycle_days'              => 10,
                'is_active'               => true,
            ],
        );
    }

    // ============================================================
    //  Helpers — Percentages
    // ============================================================

    public function getInstantPercent(int $level): float
    {
        return (float) match ($level) {
            Referral::LEVEL_1 => $this->instant_level_1_percent,
            Referral::LEVEL_2 => $this->instant_level_2_percent,
            default           => 0.0,
        };
    }

    public function getCyclePercent(int $level): float
    {
        return (float) match ($level) {
            Referral::LEVEL_1 => $this->cycle_level_1_percent,
            Referral::LEVEL_2 => $this->cycle_level_2_percent,
            default           => 0.0,
        };
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getInstantLevel1LabelAttribute(): string
    {
        return $this->instant_level_1_percent . '%';
    }

    public function getInstantLevel2LabelAttribute(): string
    {
        return $this->instant_level_2_percent . '%';
    }

    public function getCycleLevel1LabelAttribute(): string
    {
        return $this->cycle_level_1_percent . '%';
    }

    public function getCycleLevel2LabelAttribute(): string
    {
        return $this->cycle_level_2_percent . '%';
    }
}
