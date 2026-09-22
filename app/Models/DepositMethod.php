<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepositMethod extends Model
{
    use HasFactory;

    // ============================================================
    //  Constants — العملات
    // ============================================================
    public const CURRENCY_NSP = 'NSP';
    public const CURRENCY_USD = 'USD';

    // ============================================================
    //  Constants — أنواع البوابات
    // ============================================================
    public const GATEWAY_SYRIATEL = 'syriatel';
    public const GATEWAY_SHAMCASH = 'shamcash';
    public const GATEWAY_MANUAL   = 'manual';

    // ============================================================
    //  Configuration
    // ============================================================
    protected $table = 'deposit_methods';

    protected $fillable = [
        'name',
        'code',
        'icon',
        'currency',
        'gateway_type',
        'account_number',
        'account_name',
        'receiver_gsm',
        'receiver_address',
        'instructions',
        'details',
        'min_amount',
        'max_amount',
        'commission_percent',
        'is_active',
        'auto_verify',
        'sort_order',
    ];

    protected $casts = [
        'details'            => 'array',
        'min_amount'         => 'decimal:2',
        'max_amount'         => 'decimal:2',
        'commission_percent' => 'decimal:2',
        'is_active'          => 'boolean',
        'auto_verify'        => 'boolean',
        'sort_order'         => 'integer',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function userPaymentAccounts(): HasMany
    {
        return $this->hasMany(UserPaymentAccount::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_payment_account_id');
    }

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeForCurrency($query, string $currency)
    {
        return $query->where('currency', strtoupper($currency));
    }

    public function scopeOfCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    public function scopeOfGateway($query, string $gatewayType)
    {
        return $query->where('gateway_type', $gatewayType);
    }

    public function scopeAutoVerify($query)
    {
        return $query->where('auto_verify', true);
    }

    // ============================================================
    //  Helpers — Gateway
    // ============================================================

    public function isSyriatel(): bool
    {
        return $this->gateway_type === self::GATEWAY_SYRIATEL;
    }

    public function isShamCash(): bool
    {
        return $this->gateway_type === self::GATEWAY_SHAMCASH;
    }

    public function isManual(): bool
    {
        return $this->gateway_type === self::GATEWAY_MANUAL;
    }

    public function supportsAutoVerify(): bool
    {
        return $this->auto_verify && ! $this->isManual();
    }

    // ============================================================
    //  Helpers — Validation
    // ============================================================

    public function isValidAmount(float $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        if ($this->min_amount > 0 && $amount < (float) $this->min_amount) {
            return false;
        }

        if ($this->max_amount > 0 && $amount > (float) $this->max_amount) {
            return false;
        }

        return true;
    }

    public function getAmountValidationError(float $amount): ?string
    {
        if ($amount <= 0) {
            return 'المبلغ يجب أن يكون رقماً موجباً.';
        }

        if ($this->min_amount > 0 && $amount < (float) $this->min_amount) {
            return 'الحد الأدنى: ' . number_format((float) $this->min_amount, 0) . ' ' . $this->currency;
        }

        if ($this->max_amount > 0 && $amount > (float) $this->max_amount) {
            return 'الحد الأقصى: ' . number_format((float) $this->max_amount, 0) . ' ' . $this->currency;
        }

        return null;
    }

    // ============================================================
    //  Helpers — Commission
    // ============================================================

    public function calculateCommission(float $amount): float
    {
        if ($this->commission_percent <= 0) {
            return 0.0;
        }

        return round($amount * ((float) $this->commission_percent / 100), 2);
    }

    public function amountAfterCommission(float $amount): float
    {
        return round($amount - $this->calculateCommission($amount), 2);
    }

    public function hasCommission(): bool
    {
        return $this->commission_percent > 0;
    }

    // ============================================================
    //  Helpers — Status
    // ============================================================

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function isInactive(): bool
    {
        return ! $this->is_active;
    }

    // ============================================================
    //  Helpers — Details
    // ============================================================

    public function getDetail(string $key, mixed $default = null): mixed
    {
        return $this->details[$key] ?? $default;
    }

    public function setDetail(string $key, mixed $value): void
    {
        $details = $this->details ?? [];
        $details[$key] = $value;
        $this->details = $details;
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getFullNameAttribute(): string
    {
        return ($this->icon ?? '💰') . ' ' . $this->name;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? '🟢 نشطة' : '🔴 معطّلة';
    }

    public function getCurrencyIconAttribute(): string
    {
        return match (strtoupper($this->currency)) {
            self::CURRENCY_NSP => '🇸🇾',
            self::CURRENCY_USD => '💵',
            default            => '💰',
        };
    }

    public function getLimitsLabelAttribute(): string
    {
        $min = number_format((float) $this->min_amount, 0);
        $max = number_format((float) $this->max_amount, 0);

        if ($this->min_amount == 0 && $this->max_amount == 0) {
            return 'بدون حدود';
        }

        if ($this->min_amount == 0) {
            return "حتى {$max}";
        }

        if ($this->max_amount == 0) {
            return "من {$min}";
        }

        return "{$min} - {$max}";
    }

    public function getGatewayLabelAttribute(): string
    {
        return match ($this->gateway_type) {
            self::GATEWAY_SYRIATEL => '📱 سيرياتيل',
            self::GATEWAY_SHAMCASH => '🏦 شام كاش',
            self::GATEWAY_MANUAL   => '✋ يدوي',
            default                => '❔',
        };
    }

    // ============================================================
    //  Boot
    // ============================================================

    protected static function booted(): void
    {
        static::creating(function (self $method) {
            if (empty($method->code) && ! empty($method->name)) {
                $method->code = strtolower(str_replace(' ', '_', $method->name));
            }
        });
    }
}
