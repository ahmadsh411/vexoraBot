<?php

namespace App\Models;

use App\Models\Concerns\HasLocking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasLocking;

    // ============================================================
    //  Constants — الأنواع
    // ============================================================
    public const TYPE_DEPOSIT          = 'deposit';
    public const TYPE_WITHDRAW         = 'withdraw';
    public const TYPE_DEPOSIT_USD      = 'deposit_usd';
    public const TYPE_DEPOSIT_BONUS    = 'deposit_bonus';
    public const TYPE_WITHDRAW_USD     = 'withdraw_usd';
    public const TYPE_EXCHANGE         = 'exchange';
    public const TYPE_ICHANCY_DEPOSIT  = 'ichancy_deposit';
    public const TYPE_ICHANCY_WITHDRAW = 'ichancy_withdraw';
    public const TYPE_COMMISSION       = 'commission';
    public const TYPE_REFUND           = 'refund';
    public const TYPE_ADMIN_CREDIT     = 'admin_credit';
    public const TYPE_ADMIN_DEBIT      = 'admin_debit';

    // ============================================================
    //  Constants — الحالات
    // ============================================================
    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED    = 'failed';

    // ============================================================
    //  Configuration
    // ============================================================
    protected $fillable = [
        'reference',
        'user_id',
        'from_wallet_id',
        'to_wallet_id',
        'user_payment_account_id',
        'user_account_number',
        'type',
        'from_currency',
        'to_currency',
        'amount_from',
        'amount_to',
        'exchange_rate',
        'commission_amount',
        'status',
        'ichancy_transaction_id',
        'external_reference',
        'admin_id',
        'notes',
        'metadata',
        'proof_file',
        'approved_at',
        'completed_at',
        'rejected_at',
        'failed_at',
    ];

    protected $casts = [
        'amount_from'       => 'decimal:2',
        'amount_to'         => 'decimal:2',
        'exchange_rate'     => 'decimal:6',
        'commission_amount' => 'decimal:2',
        'metadata'          => 'array',
        'approved_at'       => 'datetime',
        'completed_at'      => 'datetime',
        'rejected_at'       => 'datetime',
        'failed_at'         => 'datetime',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'from_wallet_id');
    }

    public function toWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function userPaymentAccount(): BelongsTo
    {
        return $this->belongsTo(UserPaymentAccount::class);
    }

    // ============================================================
    //  Scopes — Status
    // ============================================================

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    // ============================================================
    //  Scopes — Type
    // ============================================================

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeDeposits($query)
    {
        return $query->whereIn('type', [
            self::TYPE_DEPOSIT,
            self::TYPE_DEPOSIT_USD,
            self::TYPE_ICHANCY_DEPOSIT,
        ]);
    }

    public function scopeWithdrawals($query)
    {
        return $query->whereIn('type', [
            self::TYPE_WITHDRAW,
            self::TYPE_WITHDRAW_USD,
            self::TYPE_ICHANCY_WITHDRAW,
        ]);
    }

    public function scopeAdjustments($query)
    {
        return $query->whereIn('type', [
            self::TYPE_ADMIN_CREDIT,
            self::TYPE_ADMIN_DEBIT,
        ]);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('created_at');
    }

    // ============================================================
    //  Helpers — Status
    // ============================================================

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function canBeApproved(): bool
    {
        return $this->isPending();
    }

    public function canBeRejected(): bool
    {
        return $this->isPending();
    }

    public function canBeCancelled(): bool
    {
        return $this->isPending();
    }

    // ============================================================
    //  Helpers — Type
    // ============================================================

    public function isDeposit(): bool
    {
        return in_array($this->type, [
            self::TYPE_DEPOSIT,
            self::TYPE_DEPOSIT_USD,
            self::TYPE_ICHANCY_DEPOSIT,
        ], true);
    }

    public function isWithdrawal(): bool
    {
        return in_array($this->type, [
            self::TYPE_WITHDRAW,
            self::TYPE_WITHDRAW_USD,
            self::TYPE_ICHANCY_WITHDRAW,
        ], true);
    }

    public function isExchange(): bool
    {
        return $this->type === self::TYPE_EXCHANGE;
    }

    public function isAdjustment(): bool
    {
        return in_array($this->type, [
            self::TYPE_ADMIN_CREDIT,
            self::TYPE_ADMIN_DEBIT,
        ], true);
    }

    public function isCredit(): bool
    {
        return in_array($this->type, [
            self::TYPE_DEPOSIT,
            self::TYPE_DEPOSIT_USD,
            self::TYPE_ICHANCY_DEPOSIT,
            self::TYPE_ADMIN_CREDIT,
            self::TYPE_REFUND,
            self::TYPE_COMMISSION,
        ], true);
    }

    // ============================================================
    //  Helpers — Amounts
    // ============================================================

    public function getEffectiveAmount(): float
    {
        return $this->isCredit()
            ? (float) $this->amount_to
            : (float) $this->amount_from;
    }

    public function getEffectiveCurrency(): ?string
    {
        return $this->isCredit()
            ? $this->to_currency
            : $this->from_currency;
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => '🟡 معلّق',
            self::STATUS_APPROVED  => '🟢 موافَق',
            self::STATUS_COMPLETED => '✅ مكتمل',
            self::STATUS_REJECTED  => '🔴 مرفوض',
            self::STATUS_CANCELLED => '⚫ ملغى',
            self::STATUS_FAILED    => '❌ فشل',
            default                => '❔',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_DEPOSIT          => '📥 إيداع',
            self::TYPE_WITHDRAW         => '📤 سحب',
            self::TYPE_DEPOSIT_USD      => '📥 إيداع دولار',
            self::TYPE_WITHDRAW_USD     => '📤 سحب دولار',
            self::TYPE_EXCHANGE         => '💱 تحويل',
            self::TYPE_ICHANCY_DEPOSIT  => '🎯 تعبئة إيشانسي',
            self::TYPE_ICHANCY_WITHDRAW => '🎮 سحب إيشانسي',
            self::TYPE_COMMISSION       => '💼 عمولة',
            self::TYPE_REFUND           => '🔄 استرداد',
            self::TYPE_ADMIN_CREDIT     => '➕ إضافة إدارية',
            self::TYPE_ADMIN_DEBIT      => '➖ خصم إداري',
            default                     => '❔',
        };
    }

    public function getStatusLabel(): string
    {
        return $this->status_label;
    }

    public function typeLabel(): string
    {
        return $this->type_label;
    }

    public function statusLabel(): string
    {
        return $this->status_label;
    }

    // ============================================================
    //  Boot
    // ============================================================

    protected static function booted(): void
    {
        static::creating(function (self $transaction) {
            if (empty($transaction->reference)) {
                $transaction->reference = self::generateReference();
            }
        });
    }

    // ============================================================
    //  Private
    // ============================================================

    private static function generateReference(): string
    {
        do {
            $reference = 'TXN-' . strtoupper(Str::random(12));
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }
}
