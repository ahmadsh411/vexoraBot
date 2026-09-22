<?php

namespace App\Services\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * أدوات مساعدة للخدمات المالية.
 *
 * - تنفيذ آمن داخل transaction
 * - قفل تلقائي عند الحاجة
 * - تسجيل الأخطاء
 */
trait HasLockingHelpers
{
    /**
     * تنفيذ عملية مالية داخل transaction.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    protected function runInTransaction(callable $callback, int $attempts = 3): mixed
    {
        return DB::transaction($callback, $attempts);
    }

    /**
     * قفل نموذج وإعادة تحميله.
     *
     * @template T of Model
     * @param T $model
     * @return T
     */
    protected function lockAndRefresh(Model $model): Model
    {
        $locked = $model::query()
            ->whereKey($model->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $locked;
    }

    /**
     * قفل نموذج بقيمة محددة في عمود.
     *
     * @template T of Model
     * @param class-string<T> $modelClass
     * @return T|null
     */
    protected function lockWhere(string $modelClass, array $conditions): ?Model
    {
        $query = $modelClass::query();

        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }

        return $query->lockForUpdate()->first();
    }

    /**
     * قفل نموذج بقيمة محددة أو رمي استثناء.
     *
     * @template T of Model
     * @param class-string<T> $modelClass
     * @return T
     */
    protected function lockWhereOrFail(string $modelClass, array $conditions, string $message = 'السجل غير موجود'): Model
    {
        $model = $this->lockWhere($modelClass, $conditions);

        if (! $model) {
            throw new \RuntimeException($message);
        }

        return $model;
    }

    /**
     * تسجيل خطأ مالي.
     */
    protected function logFinancialError(string $operation, array $context, \Throwable $e): void
    {
        Log::error("Financial operation failed: {$operation}", array_merge($context, [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]));
    }

    /**
     * تسجيل عملية مالية ناجحة.
     */
    protected function logFinancialSuccess(string $operation, array $context): void
    {
        Log::info("Financial operation: {$operation}", $context);
    }
}
