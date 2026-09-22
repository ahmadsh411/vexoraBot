<?php

namespace App\Models\Concerns;

use App\Models\AdminAction;
use App\Models\TransactionAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * تسجيل تلقائي لكل إجراء على العمليات المالية.
 *
 * @mixin Model
 */
trait HasAudit
{
    /**
     * أحداث يجب تسجيلها.
     */
    protected static array $auditedEvents = [
        'created',
        'updated',
        'deleted',
    ];

    /**
     * Boot الـ Trait.
     */
    public static function bootHasAudit(): void
    {
        static::created(function (Model $model) {
            if ($model->shouldAudit('created')) {
                $model->recordAudit('created', null, $model->getAttributes());
            }
        });

        static::updated(function (Model $model) {
            if ($model->shouldAudit('updated')) {
                $model->recordAudit('updated', $model->getOriginal(), $model->getChanges());
            }
        });

        static::deleted(function (Model $model) {
            if ($model->shouldAudit('deleted')) {
                $model->recordAudit('deleted', $model->getOriginal(), null);
            }
        });
    }

    /**
     * هل نسجّل هذا الحدث؟
     */
    protected function shouldAudit(string $event): bool
    {
        return in_array($event, static::$auditedEvents, true);
    }

    /**
     * سجّل الحدث.
     */
    protected function recordAudit(string $event, ?array $before, ?array $after): void
    {
        try {
            $actor = Auth::user();

            TransactionAudit::create([
                'transaction_id' => $this->getKey(),
                'user_id'        => $this->getAttribute('user_id'),
                'actor_id'       => $actor?->id,
                'actor_type'     => $actor ? ($actor->is_admin ? 'admin' : 'user') : 'system',
                'event'          => $event,
                'from_status'    => $before['status'] ?? null,
                'to_status'      => $after['status'] ?? null,
                'currency'       => $this->getAttribute('from_currency')
                    ?? $this->getAttribute('to_currency'),
                'payload'        => [
                    'before' => $this->sanitizeAuditPayload($before),
                    'after'  => $this->sanitizeAuditPayload($after),
                ],
                'ip_address'     => request()?->ip(),
                'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
                'request_id'     => request()?->header('X-Request-ID'),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('HasAudit: failed to record audit', [
                'model' => static::class,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * إخفاء الحقول الحساسة من الـ payload.
     */
    protected function sanitizeAuditPayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $sensitive = ['password', 'password_encrypted', 'token', 'api_key'];

        foreach ($sensitive as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = '***REDACTED***';
            }
        }

        return $payload;
    }
}
