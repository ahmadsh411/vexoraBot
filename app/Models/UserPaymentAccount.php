<?php

namespace App\Models;

use App\Models\Concerns\HasLocking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPaymentAccount extends Model
{
    use HasFactory;
    use HasLocking;

    protected $table = 'user_payment_accounts';

    protected $fillable = [
        'user_id',
        'deposit_method_id',
        'account_number',
        'account_name',
        'total_deposited',
        'total_withdrawn',
        'available_balance',
        'deposits_count',
        'withdrawals_count',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'total_deposited'   => 'decimal:2',
        'total_withdrawn'   => 'decimal:2',
        'available_balance' => 'decimal:2',
        'deposits_count'    => 'integer',
        'withdrawals_count' => 'integer',
        'is_active'         => 'boolean',
        'last_used_at'      => 'datetime',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function depositMethod(): BelongsTo
    {
        return $this->belongsTo(DepositMethod::class, 'deposit_method_id');
    }

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithBalance($query)
    {
        return $query->where('available_balance', '>', 0);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('last_used_at')->orderByDesc('id');
    }

    // ============================================================
    //  Helpers — Validation
    // ============================================================

    public function canWithdraw(float $amount): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($amount <= 0) {
            return false;
        }

        return (float) $this->available_balance >= $amount;
    }

    public function getWithdrawError(float $amount): ?string
    {
        if (! $this->is_active) {
            return 'هذا الحساب غير نشط.';
        }

        if ($amount <= 0) {
            return 'المبلغ غير صالح.';
        }

        if ((float) $this->available_balance < $amount) {
            return 'الرصيد المتاح: ' . number_format((float) $this->available_balance, 2)
                . ' — المطلوب: ' . number_format($amount, 2);
        }

        return null;
    }

    // ============================================================
    //  Helpers — Updates
    // ============================================================

    public function addDeposit(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('المبلغ يجب أن يكون موجباً.');
        }

        $this->increment('total_deposited', $amount);
        $this->increment('available_balance', $amount);
        $this->increment('deposits_count');
        $this->update(['last_used_at' => now()]);
        $this->refresh();
    }

    public function addWithdrawal(float $amount): void
    {
        if (! $this->canWithdraw($amount)) {
            throw new \RuntimeException(
                $this->getWithdrawError($amount) ?? 'لا يمكن السحب.'
            );
        }

        $this->increment('total_withdrawn', $amount);
        $this->decrement('available_balance', $amount);
        $this->increment('withdrawals_count');
        $this->update(['last_used_at' => now()]);
        $this->refresh();
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getDisplayNameAttribute(): string
    {
        $method = $this->depositMethod;
        $icon = $method?->icon ?? '💰';
        $name = $method?->name ?? 'طريقة';

        return "{$icon} {$name} — {$this->account_number}";
    }

    public function getAvailableLabelAttribute(): string
    {
        return number_format((float) $this->available_balance, 2);
    }
}
