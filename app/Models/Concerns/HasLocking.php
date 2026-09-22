<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * أدوات Pessimistic Locking للعمليات المالية.
 *
 * @mixin Model
 */
trait HasLocking
{
    /**
     * جلب السجل مع قفل للكتابة.
     */
    public static function findWithLock(int $id): ?static
    {
        return static::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
    }

    /**
     * جلب السجل مع قفل للكتابة أو رمي استثناء.
     */
    public static function findWithLockOrFail(int $id): static
    {
        $model = static::findWithLock($id);

        if (! $model) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())
                ->setModel(static::class, [$id]);
        }

        return $model;
    }

    /**
     * Scope: قفل للكتابة.
     */
    public function scopeLocked(Builder $query): Builder
    {
        return $query->lockForUpdate();
    }

    /**
     * Scope: جلب بالقفل.
     */
    public function scopeForUpdate(Builder $query): Builder
    {
        return $query->lockForUpdate();
    }
}
