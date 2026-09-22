<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable
{
    use HasFactory;
    use SoftDeletes;

    // ============================================================
    //  Constants — الأدوار
    // ============================================================
    public const ROLE_USER        = 'user';
    public const ROLE_ADMIN       = 'admin';
    public const ROLE_SUPER_ADMIN = 'super_admin';

    // ============================================================
    //  Constants — أنواع الإحالة
    // ============================================================
    public const REFERRAL_INSTANT = 'instant';
    public const REFERRAL_CYCLE   = 'cycle';

    // ============================================================
    //  Constants — مفاتيح التشفير
    // ============================================================
    public const PASSWORD_KEY_V1 = 'v1';

    // ============================================================
    //  Configuration
    // ============================================================
    protected $fillable = [
        'telegram_id',
        'telegram_username',
        'username',
        'password_encrypted',
        'password_key_id',
        'ichancy_player_id',
        'first_name',
        'last_name',
        'is_active',
        'is_admin',
        'is_super_admin',
        'last_login_at',
        'admin_seen_at',
        'admin_welcome_message_id',
        'password_changed_at',
        'referral_code',
        'referral_type',
        'referral_chosen_at',
        'referred_by',
        'referred_by_level_2',
        'referrals_count',
        'referral_earnings',
    ];

    protected $hidden = [
        'password_encrypted',
        'password_key_id',
    ];

    protected $casts = [
        'telegram_id'         => 'integer',
        'is_active'           => 'boolean',
        'is_admin'            => 'boolean',
        'is_super_admin'      => 'boolean',
        'last_login_at'       => 'datetime',
        'admin_seen_at'       => 'datetime',
        'admin_welcome_message_id' => 'integer',
        'password_changed_at' => 'datetime',
        'referral_chosen_at'  => 'datetime',
        'referrals_count'     => 'integer',
        'referral_earnings'   => 'decimal:2',
    ];

    protected $attributes = [
        'is_active'         => true,
        'is_admin'          => false,
        'is_super_admin'    => false,
        'referrals_count'   => 0,
        'referral_earnings' => 0,
        'password_key_id'   => self::PASSWORD_KEY_V1,
    ];

    // ============================================================
    //  Relations — Wallet & Transactions
    // ============================================================

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'user_id')
            ->where('type', Wallet::TYPE_USER);
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class, 'user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    // ============================================================
    //  Relations — Payment Accounts
    // ============================================================

    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(UserPaymentAccount::class);
    }

    public function activePaymentAccounts(): HasMany
    {
        return $this->hasMany(UserPaymentAccount::class)
            ->where('is_active', true);
    }

    // ============================================================
    //  Relations — Referrals
    // ============================================================

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrerLevel2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_level_2');
    }

    public function directReferrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function level2Referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by_level_2');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referralRewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class, 'referrer_id');
    }

    public function referralCycleRewards(): HasMany
    {
        return $this->hasMany(ReferralCycleReward::class, 'referrer_id');
    }

    // ============================================================
    //  Relations — Wheel
    // ============================================================

    public function wheelStates(): HasMany
    {
        return $this->hasMany(WheelUserState::class);
    }

    public function wheelSpins(): HasMany
    {
        return $this->hasMany(WheelSpin::class);
    }

    // ============================================================
    //  Relations — Audits
    // ============================================================

    public function adminActions(): HasMany
    {
        return $this->hasMany(AdminAction::class, 'admin_id');
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

    public function scopeAdmins($query)
    {
        return $query->where('is_admin', true);
    }

    public function scopeSuperAdmins($query)
    {
        return $query->where('is_admin', true)
            ->where('is_super_admin', true);
    }

    public function scopeRegularAdmins($query)
    {
        return $query->where('is_admin', true)
            ->where('is_super_admin', false);
    }

    public function scopeRegularUsers($query)
    {
        return $query->where('is_admin', false);
    }

    public function scopeWithTelegram($query)
    {
        return $query->whereNotNull('telegram_id');
    }

    public function scopeUnseen($query)
    {
        return $query->whereNull('admin_seen_at');
    }

    public function scopeByReferralCode($query, string $code)
    {
        return $query->where('referral_code', $code);
    }

    public function scopeReferrers($query)
    {
        return $query->where('referrals_count', '>', 0);
    }

    // ============================================================
    //  Helpers — Roles
    // ============================================================

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_admin && (bool) $this->is_super_admin;
    }

    public function isRegularAdmin(): bool
    {
        return (bool) $this->is_admin && ! $this->is_super_admin;
    }

    public function isRegularUser(): bool
    {
        return ! (bool) $this->is_admin;
    }

    public function hasRole(string $role): bool
    {
        return match ($role) {
            self::ROLE_SUPER_ADMIN => $this->isSuperAdmin(),
            self::ROLE_ADMIN       => $this->is_admin,
            self::ROLE_USER        => ! $this->is_admin,
            default                => false,
        };
    }

    public function getRole(): string
    {
        if ($this->isSuperAdmin()) return self::ROLE_SUPER_ADMIN;
        if ($this->is_admin)       return self::ROLE_ADMIN;
        return self::ROLE_USER;
    }

    // ============================================================
    //  Helpers — Referrals
    // ============================================================

    public function hasChosenReferralType(): bool
    {
        return ! empty($this->referral_type);
    }

    public function isReferred(): bool
    {
        return ! empty($this->referred_by);
    }

    // ============================================================
    //  Helpers — Wallet
    // ============================================================

    public function getWalletAttribute(): ?Wallet
    {
        return $this->relationLoaded('wallet')
            ? $this->getRelation('wallet')
            : $this->wallet()->first();
    }

    public function hasEnoughBalance(string $currency, float $amount): bool
    {
        $wallet = $this->wallet;
        return $wallet && $wallet->hasEnough($currency, $amount);
    }

    // ============================================================
    //  Password Helpers
    // ============================================================

    /**
     * تعيين كلمة مرور (مشفرة بمفتاح منفصل).
     */
    public function setPassword(string $plainPassword, ?string $keyId = null): void
    {
        $service = app(\App\Services\PasswordEncryptionService::class);
        $result = $service->encrypt($plainPassword);

        $this->password_encrypted = $result['encrypted'];
        $this->password_key_id    = $result['key_id'];
        $this->password_changed_at = now();
    }

    public function getPassword(string $reason = '', ?int $accessedBy = null): ?string
    {
        if (empty($this->password_encrypted)) {
            return null;
        }

        $service = app(\App\Services\PasswordEncryptionService::class);
        $password = $service->decrypt($this->password_encrypted, $this->password_key_id);

        if ($password !== null) {
            // ✅ تسجيل الوصول
            \App\Models\SensitiveAccessLog::log(
                userId: $this->id,
                accessedBy: $accessedBy ?? auth()->id(),
                resourceType: 'password',
                resourceId: $this->id,
                action: 'decrypt',
                reason: $reason,
            );
        }

        return $password;
    }

    public function checkPassword(string $plainPassword): bool
    {
        if (empty($this->password_encrypted)) {
            return false;
        }

        $service = app(\App\Services\PasswordEncryptionService::class);

        return $service->check($this->password_encrypted, $plainPassword);
    }

    /**
     * كلمة المرور — داخلية بدون تسجيل.
     */
    protected function getPasswordInternal(): ?string
    {
        if (empty($this->password_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->password_encrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getFullNameAttribute(): string
    {
        $name = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        return $name !== '' ? $name : $this->username;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: $this->username;
    }

    public function getReferralLinkAttribute(): ?string
    {
        if (! $this->referral_code) {
            return null;
        }

        $botUsername = config('services.telegram.bot_username', 'VexoraDeskBot');

        return "https://t.me/{$botUsername}?start=ref_{$this->referral_code}";
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->getRole()) {
            self::ROLE_SUPER_ADMIN => '👑 مشرف أساسي',
            self::ROLE_ADMIN       => '🛡️ أدمن عادي',
            default                => '👤 مستخدم',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? '🟢 نشط' : '🔴 موقوف';
    }

    public function getReferralTypeLabelAttribute(): string
    {
        return match ($this->referral_type) {
            self::REFERRAL_INSTANT => '⚡ فوري',
            self::REFERRAL_CYCLE   => '📅 دوري',
            default                => '❔ لم يُحدَّد',
        };
    }

    // ============================================================
    //  Boot
    // ============================================================

    protected static function booted(): void
    {
        static::creating(function (self $user) {
            if (empty($user->password_key_id)) {
                $user->password_key_id = self::PASSWORD_KEY_V1;
            }
        });
    }

    public function ichancyAccount()
    {
        return $this->hasOne(\App\Models\IChancyAccount::class);
    }

    public function gemBalance(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\GemBalance::class);
    }

    public function gemTransactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\GemTransaction::class);
    }
}
