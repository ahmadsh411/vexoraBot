<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionAudit extends Model
{
    use HasFactory;

    // ============================================================
    //  Constants — الأحداث
    // ============================================================
    public const EVENT_CREATED   = 'created';
    public const EVENT_UPDATED   = 'updated';
    public const EVENT_DELETED   = 'deleted';
    public const EVENT_APPROVED  = 'approved';
    public const EVENT_REJECTED  = 'rejected';
    public const EVENT_COMPLETED = 'completed';
    public const EVENT_FAILED    = 'failed';
    public const EVENT_REFUNDED  = 'refunded';

    // ============================================================
    //  Constants — أنواع الفاعل
    // ============================================================
    public const ACTOR_SYSTEM    = 'system';
    public const ACTOR_ADMIN     = 'admin';
    public const ACTOR_USER      = 'user';
    public const ACTOR_API       = 'api';
    public const ACTOR_SCHEDULER = 'scheduler';

    // ============================================================
    //  Configuration
    // ============================================================
    protected $table = 'transaction_audits';

    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'user_id',
        'actor_id',
        'actor_type',
        'event',
        'from_status',
        'to_status',
        'amount_before',
        'amount_after',
        'balance_before',
        'balance_after',
        'currency',
        'payload',
        'note',
        'ip_address',
        'user_agent',
        'request_id',
    ];

    protected $casts = [
        'amount_before'  => 'decimal:2',
        'amount_after'   => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after'  => 'decimal:2',
        'payload'        => 'array',
        'created_at'     => 'datetime',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeForTransaction($query, int $transactionId)
    {
        return $query->where('transaction_id', $transactionId);
    }

    public function scopeByEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    public function scopeByActor($query, int $actorId)
    {
        return $query->where('actor_id', $actorId);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('created_at');
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getEventLabelAttribute(): string
    {
        return match ($this->event) {
            self::EVENT_CREATED   => '🆕 إنشاء',
            self::EVENT_UPDATED   => '✏️ تحديث',
            self::EVENT_DELETED   => '🗑️ حذف',
            self::EVENT_APPROVED  => '✅ موافقة',
            self::EVENT_REJECTED  => '❌ رفض',
            self::EVENT_COMPLETED => '🏁 إكمال',
            self::EVENT_FAILED    => '💥 فشل',
            self::EVENT_REFUNDED  => '↩️ استرداد',
            default               => '❔',
        };
    }

    public function getActorTypeLabelAttribute(): string
    {
        return match ($this->actor_type) {
            self::ACTOR_SYSTEM    => '⚙️ النظام',
            self::ACTOR_ADMIN     => '👑 أدمن',
            self::ACTOR_USER      => '👤 مستخدم',
            self::ACTOR_API       => '🔌 API',
            self::ACTOR_SCHEDULER => '⏰ مجدول',
            default               => '❔',
        };
    }
}
