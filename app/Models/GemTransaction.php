<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GemTransaction extends Model
{
    public const TYPE_EARN   = 'earn';
    public const TYPE_SPEND  = 'spend';
    public const TYPE_REFUND = 'refund';

    public const SOURCE_DEPOSIT  = 'deposit';
    public const SOURCE_EXCHANGE = 'exchange';
    public const SOURCE_WHEEL    = 'wheel';
    public const SOURCE_ADMIN    = 'admin';

    protected $fillable = [
        'user_id',
        'amount',
        'type',
        'source',
        'reference_id',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'amount'   => 'integer',
        'metadata' => 'array',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeEarned($query)
    {
        return $query->where('type', self::TYPE_EARN);
    }

    public function scopeSpent($query)
    {
        return $query->where('type', self::TYPE_SPEND);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('created_at');
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getSignedAmountAttribute(): string
    {
        return ($this->amount > 0 ? '+' : '') . $this->amount;
    }

    public function getSourceLabelAttribute(): string
    {
        return match ($this->source) {
            self::SOURCE_DEPOSIT  => '📥 إيداع',
            self::SOURCE_EXCHANGE => '💱 استبدال',
            self::SOURCE_WHEEL    => '🎡 عجلة',
            self::SOURCE_ADMIN    => '👮 إدارة',
            default                => '❓ ' . $this->source,
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_EARN   => '🟢 اكتساب',
            self::TYPE_SPEND  => '🔴 استهلاك',
            self::TYPE_REFUND => '♻️ استرجاع',
            default            => '❓ ' . $this->type,
        };
    }
}
