<?php

namespace App\Models;

use App\Models\Concerns\HasLocking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WheelUserState extends Model
{
    use HasFactory;
    use HasLocking;

    protected $fillable = [
        'user_id',
        'wheel_id',
        'available_spins',
        'total_spins_used',
        'total_won_amount',
        'spins_today',
        'last_spin_date',
    ];

    protected $casts = [
        'available_spins'  => 'integer',
        'total_spins_used'  => 'integer',
        'total_won_amount'  => 'decimal:2',
        'spins_today'       => 'integer',
        'last_spin_date'    => 'date',
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

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForWheel($query, int $wheelId)
    {
        return $query->where('wheel_id', $wheelId);
    }

    public function scopeHasSpins($query)
    {
        return $query->where('available_spins', '>', 0);
    }

    // ============================================================
    //  Helpers — Daily Reset
    // ============================================================

    /**
     * هل انقضى يوم جديد؟
     */
    public function isNewDay(): bool
    {
        if ($this->last_spin_date === null) {
            return true;
        }

        return ! $this->last_spin_date->isToday();
    }

    /**
     * تصفير العداد اليومي إذا لزم.
     */
    public function resetDailyIfNeeded(): void
    {
        if ($this->isNewDay()) {
            $this->update([
                'spins_today'    => 0,
                'last_spin_date' => today(),
            ]);
        }
    }

    // ============================================================
    //  Helpers — Spins
    // ============================================================

    public function canSpin(Wheel $wheel): bool
    {
        return $this->available_spins > 0
            && $this->spins_today < $wheel->daily_limit;
    }

    public function getRemainingToday(Wheel $wheel): int
    {
        return max(0, $wheel->daily_limit - $this->spins_today);
    }

    /**
     * خصم لفة واحدة.
     */
    public function consumeSpin(): void
    {
        if ($this->available_spins <= 0) {
            throw new \RuntimeException('لا توجد لفات متاحة.');
        }

        $this->decrement('available_spins');
        $this->increment('total_spins_used');
        $this->increment('spins_today');
        $this->update(['last_spin_date' => today()]);
        $this->refresh();
    }

    /**
     * إضافة لفات.
     */
    public function addSpins(int $count, int $maxStored = 100): int
    {
        if ($count <= 0) {
            return 0;
        }

        $current = (int) $this->available_spins;
        $newTotal = min($current + $count, $maxStored);
        $actualAdded = $newTotal - $current;

        if ($actualAdded > 0) {
            $this->update(['available_spins' => $newTotal]);
            $this->refresh();
        }

        return $actualAdded;
    }

    /**
     * إضافة مبلغ ربح.
     */
    public function addWonAmount(float $amount): void
    {
        $this->increment('total_won_amount', $amount);
        $this->refresh();
    }

    // ============================================================
    //  Static Helpers
    // ============================================================

    public static function getOrCreateFor(int $userId, int $wheelId): self
    {
        return static::firstOrCreate(
            [
                'user_id'  => $userId,
                'wheel_id' => $wheelId,
            ],
            [
                'available_spins'  => 0,
                'total_spins_used' => 0,
                'total_won_amount' => 0,
                'spins_today'      => 0,
            ],
        );
    }

    /**
     * جلب مع قفل.
     */
    public static function findWithLock(int $userId, int $wheelId): self
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('wheel_id', $wheelId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
