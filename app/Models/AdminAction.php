<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdminAction extends Model
{
    use HasFactory;

    // ============================================================
    //  Constants — الأنواع الشائعة
    // ============================================================
    public const ACTION_USER_ACTIVATE     = 'user.activate';
    public const ACTION_USER_DEACTIVATE   = 'user.deactivate';
    public const ACTION_USER_DELETE       = 'user.delete';
    public const ACTION_USER_RESTORE      = 'user.restore';
    public const ACTION_USER_FORCE_DELETE = 'user.force_delete';
    public const ACTION_USER_PROMOTE      = 'user.promote';
    public const ACTION_USER_DEMOTE       = 'user.demote';

    public const ACTION_BALANCE_ADD    = 'balance.add';
    public const ACTION_BALANCE_SUB    = 'balance.sub';

    public const ACTION_DEPOSIT_APPROVE  = 'deposit.approve';
    public const ACTION_DEPOSIT_REJECT   = 'deposit.reject';
    public const ACTION_WITHDRAW_APPROVE = 'withdraw.approve';
    public const ACTION_WITHDRAW_REJECT  = 'withdraw.reject';

    public const ACTION_WHEEL_GRANT_SPIN  = 'wheel.grant_spin';
    public const ACTION_WHEEL_RESET_DAILY = 'wheel.reset_daily';

    public const ACTION_SETTING_UPDATE = 'setting.update';
    public const ACTION_CYCLE_CLOSE    = 'cycle.close';
    public const ACTION_CYCLE_CREATE   = 'cycle.create';

    // ============================================================
    //  Configuration
    // ============================================================
    protected $table = 'admin_actions';

    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'action',
        'target_type',
        'target_id',
        'changes',
        'reason',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'changes'    => 'array',
        'created_at' => 'datetime',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * علاقة Polymorphic للهدف (User, Transaction, إلخ).
     */
    public function target(): MorphTo
    {
        return $this->morphTo('target', 'target_type', 'target_id');
    }

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeByAdmin($query, int $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeForTarget($query, string $targetType, int $targetId)
    {
        return $query->where('target_type', $targetType)
            ->where('target_id', $targetId);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('created_at');
    }

    // ============================================================
    //  Static Helpers
    // ============================================================

    /**
     * تسجيل إجراء إداري.
     */
    public static function log(
        int $adminId,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $changes = null,
        ?string $reason = null,
    ): self {
        return static::create([
            'admin_id'    => $adminId,
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'changes'     => $changes,
            'reason'      => $reason,
            'ip_address'  => request()?->ip(),
            'user_agent'  => substr((string) request()?->userAgent(), 0, 500),
        ]);
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_USER_ACTIVATE     => '🟢 تفعيل مستخدم',
            self::ACTION_USER_DEACTIVATE   => '🔴 إيقاف مستخدم',
            self::ACTION_USER_DELETE       => '🗑️ حذف مستخدم',
            self::ACTION_USER_RESTORE      => '♻️ استعادة مستخدم',
            self::ACTION_USER_FORCE_DELETE => '💥 حذف نهائي',
            self::ACTION_USER_PROMOTE      => '👑 ترقية',
            self::ACTION_USER_DEMOTE       => '🛡️ تخفيض',
            self::ACTION_BALANCE_ADD       => '➕ إضافة رصيد',
            self::ACTION_BALANCE_SUB       => '➖ خصم رصيد',
            self::ACTION_DEPOSIT_APPROVE   => '✅ موافقة إيداع',
            self::ACTION_DEPOSIT_REJECT    => '❌ رفض إيداع',
            self::ACTION_WITHDRAW_APPROVE  => '✅ موافقة سحب',
            self::ACTION_WITHDRAW_REJECT   => '❌ رفض سحب',
            self::ACTION_WHEEL_GRANT_SPIN  => '🎡 منح لفة',
            self::ACTION_WHEEL_RESET_DAILY => '♻️ تصفير العداد',
            self::ACTION_SETTING_UPDATE    => '⚙️ تعديل إعداد',
            self::ACTION_CYCLE_CLOSE       => '🔒 إغلاق دورة',
            self::ACTION_CYCLE_CREATE      => '🆕 إنشاء دورة',
            default                        => $this->action,
        };
    }

    public function getChangesSummaryAttribute(): string
    {
        if (empty($this->changes)) {
            return '—';
        }

        $parts = [];

        foreach ($this->changes as $field => $change) {
            $old = $change['old'] ?? '—';
            $new = $change['new'] ?? '—';
            $parts[] = "{$field}: {$old} → {$new}";
        }

        return implode(' | ', $parts);
    }
}
