<?php



// app/Models/GiftCode.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class GiftCode extends Model
{
    protected $fillable = [
        'code',
        'value',
        'currency',
        'max_uses',
        'used_count',
        'status',
        'created_by',
        'channel_id',
        'channel_msg_id',
        'expires_at',
        'note',
    ];

    protected $casts = [
        'value'        => 'decimal:2',
        'expires_at'   => 'datetime',
        'used_count'   => 'integer',
        'max_uses'     => 'integer',
        'channel_id'   => 'integer',
        'channel_msg_id' => 'integer',
    ];

    // ---------- العلاقات ----------
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(GiftRedemption::class);
    }

    // ---------- Scopes ----------
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active')
            ->whereColumn('used_count', '<', 'max_uses')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    // ---------- Helpers ----------
    public function getRemainingUsesAttribute(): int
    {
        return max(0, $this->max_uses - $this->used_count);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active'
            && ! $this->is_expired
            && $this->remaining_uses > 0;
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = 'GIFT_' . strtoupper(Str::random(8));
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
