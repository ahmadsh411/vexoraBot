<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensitiveAccessLog extends Model
{
    use HasFactory;

    // ============================================================
    //  Constants — أنواع الموارد
    // ============================================================
    public const RESOURCE_PASSWORD     = 'password';
    public const RESOURCE_PIN          = 'pin';
    public const RESOURCE_API_KEY      = 'api_key';
    public const RESOURCE_TOKEN        = 'token';
    public const RESOURCE_PERSONAL_DATA = 'personal_data';

    // ============================================================
    //  Constants — الإجراءات
    // ============================================================
    public const ACTION_READ    = 'read';
    public const ACTION_DECRYPT = 'decrypt';
    public const ACTION_UPDATE  = 'update';
    public const ACTION_DELETE  = 'delete';

    // ============================================================
    //  Configuration
    // ============================================================
    protected $table = 'sensitive_access_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'accessed_by',
        'resource_type',
        'resource_id',
        'action',
        'reason',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accessed_by');
    }

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByAccessor($query, int $accessedBy)
    {
        return $query->where('accessed_by', $accessedBy);
    }

    public function scopeByResourceType($query, string $resourceType)
    {
        return $query->where('resource_type', $resourceType);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('created_at');
    }

    // ============================================================
    //  Static Helpers
    // ============================================================

    /**
     * تسجيل وصول لبيانات حساسة.
     */
    public static function log(
        int $userId,
        ?int $accessedBy,
        string $resourceType,
        ?int $resourceId,
        string $action,
        ?string $reason = null,
    ): self {
        return static::create([
            'user_id'       => $userId,
            'accessed_by'   => $accessedBy,
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'action'        => $action,
            'reason'        => $reason,
            'ip_address'    => request()?->ip(),
            'user_agent'    => substr((string) request()?->userAgent(), 0, 500),
        ]);
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getResourceTypeLabelAttribute(): string
    {
        return match ($this->resource_type) {
            self::RESOURCE_PASSWORD      => '🔑 كلمة مرور',
            self::RESOURCE_PIN           => '🔢 PIN',
            self::RESOURCE_API_KEY       => '🔐 API Key',
            self::RESOURCE_TOKEN         => '🎫 Token',
            self::RESOURCE_PERSONAL_DATA => '📋 بيانات شخصية',
            default                      => '❔',
        };
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_READ    => '👁️ قراءة',
            self::ACTION_DECRYPT => '🔓 فك تشفير',
            self::ACTION_UPDATE  => '✏️ تعديل',
            self::ACTION_DELETE  => '🗑️ حذف',
            default              => '❔',
        };
    }
}
