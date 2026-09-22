<?php

namespace App\Models;

use App\Models\Concerns\HasLocking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wallet extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasLocking;

    // ============================================================
    //  Constants — الأنواع
    // ============================================================
    public const TYPE_MAIN  = 'main';
    public const TYPE_USER  = 'user';
    public const TYPE_AGENT = 'agent';

    // ============================================================
    //  Constants — العملات
    // ============================================================
    public const CURRENCY_NSP = 'NSP';
    public const CURRENCY_SYP = 'SYP';
    public const CURRENCY_USD = 'USD';

    // ============================================================
    //  Configuration
    // ============================================================
    protected $fillable = [
        'type',
        'user_id',
        'balance_nsp',
        'balance_usd',
        'is_locked',
        'locked_at',
        'total_deposit_nsp',
        'total_deposit_usd',
        'total_withdraw_nsp',
        'total_withdraw_usd',
        'total_commission_nsp',
        'total_commission_usd',
        'is_active',
        'is_frozen',
        'last_synced_at',
    ];

    protected $casts = [
        'balance_nsp'           => 'decimal:2',
        'balance_usd'           => 'decimal:2',
        'total_deposit_nsp'     => 'decimal:2',
        'total_deposit_usd'     => 'decimal:2',
        'total_withdraw_nsp'    => 'decimal:2',
        'total_withdraw_usd'    => 'decimal:2',
        'total_commission_nsp'  => 'decimal:2',
        'total_commission_usd'  => 'decimal:2',
        'is_locked'             => 'boolean',
        'is_active'             => 'boolean',
        'is_frozen'             => 'boolean',
        'locked_at'             => 'datetime',
        'last_synced_at'        => 'datetime',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outgoingTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'from_wallet_id');
    }

    public function incomingTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'to_wallet_id');
    }

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeMain($query)
    {
        return $query->where('type', self::TYPE_MAIN);
    }

    public function scopeForUsers($query)
    {
        return $query->where('type', self::TYPE_USER);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('is_frozen', false);
    }

    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }

    // ============================================================
    //  Helpers — Balance
    // ============================================================

    /*************  ✨ Windsurf Command ⭐  *************/
    /**
     * Get the balance of the wallet in the given currency.
     *
     * @param string $currency The currency to get the balance for.
     * @return float The balance of the wallet in the given currency.
     */
    /*******  90dc547e-14bb-45f4-8e80-b42a04119e68  *******/
    public function getBalance(string $currency): float
    {
        return match (strtoupper($currency)) {
            self::CURRENCY_NSP,
            self::CURRENCY_SYP => (float) $this->balance_nsp,

            self::CURRENCY_USD => (float) $this->balance_usd,

            default => 0.0,
        };
    }

    public function hasEnough(string $currency, float $amount): bool
    {
        return $this->getBalance($currency) >= $amount;
    }

    /**
     * إضافة رصيد (بدون قفل — استخدم داخل transaction).
     */
    public function credit(string $currency, float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Credit amount must be positive, got {$amount}");
        }

        $column = $this->columnFor($currency);
        $this->increment($column, $amount);
        $this->refresh();
    }

    /**
     * خصم رصيد (بدون قفل — استخدم داخل transaction).
     */
    public function debit(string $currency, float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Debit amount must be positive, got {$amount}");
        }

        $currentBalance = $this->getBalance($currency);

        if ($currentBalance < $amount) {
            throw new \RuntimeException(
                "رصيد غير كافٍ. المتاح: {$currentBalance} {$currency}، المطلوب: {$amount} {$currency}"
            );
        }

        $column = $this->columnFor($currency);
        $this->decrement($column, $amount);
        $this->refresh();
    }

    /**
     * إضافة إحصائية إيداع.
     */
    public function addDepositStat(string $currency, float $amount): void
    {
        $column = match (strtoupper($currency)) {
            self::CURRENCY_NSP,
            self::CURRENCY_SYP => 'total_deposit_nsp',

            self::CURRENCY_USD => 'total_deposit_usd',

            default => throw new \InvalidArgumentException("Unknown currency: {$currency}"),
        };

        $this->increment($column, $amount);
    }

    /**
     * إضافة إحصائية سحب.
     */
    public function addWithdrawStat(string $currency, float $amount): void
    {
        $column = match (strtoupper($currency)) {
            self::CURRENCY_NSP,
            self::CURRENCY_SYP => 'total_withdraw_nsp',

            self::CURRENCY_USD => 'total_withdraw_usd',

            default => throw new \InvalidArgumentException("Unknown currency: {$currency}"),
        };

        $this->increment($column, $amount);
    }

    /**
     * إضافة إحصائية عمولة.
     */
    public function addCommissionStat(string $currency, float $amount): void
    {
        $column = match (strtoupper($currency)) {
            self::CURRENCY_NSP,
            self::CURRENCY_SYP => 'total_commission_nsp',

            self::CURRENCY_USD => 'total_commission_usd',

            default => throw new \InvalidArgumentException("Unknown currency: {$currency}"),
        };

        $this->increment($column, $amount);
    }

    // ============================================================
    //  Helpers — Status
    // ============================================================

    public function isMain(): bool
    {
        return $this->type === self::TYPE_MAIN;
    }

    public function isUserWallet(): bool
    {
        return $this->type === self::TYPE_USER;
    }

    public function isFrozen(): bool
    {
        return (bool) $this->is_frozen;
    }

    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }

    public function canTransact(): bool
    {
        return $this->is_active && ! $this->is_frozen;
    }

    // ============================================================
    //  Helpers — Locking
    // ============================================================

    /**
     * قفل المحفظة (تسجيل).
     */
    public function markLocked(): void
    {
        $this->update([
            'is_locked' => true,
            'locked_at' => now(),
        ]);
    }

    /**
     * فتح المحفظة.
     */
    public function markUnlocked(): void
    {
        $this->update([
            'is_locked' => false,
            'locked_at' => null,
        ]);
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getBalanceSypFormattedAttribute(): string
    {
        return number_format((float) $this->balance_nsp, 2);
    }

    public function getBalanceUsdFormattedAttribute(): string
    {
        return number_format((float) $this->balance_usd, 2);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_MAIN  => '🏦 رئيسية',
            self::TYPE_USER  => '👤 مستخدم',
            self::TYPE_AGENT => '💼 وكيل',
            default          => '❔',
        };
    }

    // ============================================================
    //  Private
    // ============================================================

    private function columnFor(string $currency): string
    {
        return match (strtoupper($currency)) {
            self::CURRENCY_NSP,
            self::CURRENCY_SYP => 'balance_nsp',

            self::CURRENCY_USD => 'balance_usd',

            default => throw new \InvalidArgumentException("Unknown currency: {$currency}"),
        };
    }
}
