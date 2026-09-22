<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ExchangeRateService
{
    private const CACHE_TTL = 300; // 5 دقائق

    // ============================================================
    //  قراءة السعر
    // ============================================================

    public function getRate(
        string $from,
        string $to,
        bool $useCache = true,
    ): ?ExchangeRate {
        $from = strtoupper($from);
        $to   = strtoupper($to);

        if ($from === $to) {
            return null;
        }

        $cacheKey = "exchange_rate_{$from}_{$to}";

        if ($useCache) {
            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL,
                fn() => ExchangeRate::active()
                    ->between($from, $to)
                    ->latest()
                    ->first(),
            );
        }

        return ExchangeRate::active()
            ->between($from, $to)
            ->latest()
            ->first();
    }

    // ============================================================
    //  التحويل
    // ============================================================

    public function convert(
        string $from,
        string $to,
        float $amount,
        bool $withCommission = false,
    ): ?array {
        $rate = $this->getRate($from, $to);

        if (! $rate) {
            return null;
        }

        $converted = $withCommission
            ? $rate->convertWithCommission($amount)
            : $rate->convert($amount);

        return [
            'from_currency' => strtoupper($from),
            'to_currency'   => strtoupper($to),
            'amount_from'   => $amount,
            'amount_to'     => $converted,
            'rate'          => (float) $rate->rate,
            'commission'    => $rate->commissionAmount($amount),
            'rate_model'    => $rate,
        ];
    }

    // ============================================================
    //  الإدارة (Admin)
    // ============================================================

    /**
     * تعيين سعر صرف جديد (يعطّل القديم).
     */
    public function setRate(
        string $from,
        string $to,
        float $rate,
        float $commission = 0,
        ?User $updatedBy = null,
        ?string $notes = null,
    ): ExchangeRate {
        $from = strtoupper($from);
        $to   = strtoupper($to);

        // ✅ تعطيل الأسعار القديمة
        ExchangeRate::between($from, $to)
            ->update(['is_active' => false]);

        // ✅ إنشاء الجديد
        $newRate = ExchangeRate::create([
            'from_currency'      => $from,
            'to_currency'        => $to,
            'rate'               => $rate,
            'commission_percent' => $commission,
            'is_active'          => true,
            'updated_by'         => $updatedBy?->id,
            'notes'              => $notes,
        ]);

        $this->clearCache($from, $to);

        return $newRate;
    }

    /**
     * تعطيل سعر.
     */
    public function deactivate(string $from, string $to): void
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);

        ExchangeRate::between($from, $to)
            ->update(['is_active' => false]);

        $this->clearCache($from, $to);
    }

    /**
     * كل الأسعار النشطة.
     */
    public function getAllActiveRates(): array
    {
        return ExchangeRate::active()
            ->with('updater:id,username')
            ->latest()
            ->get()
            ->map(fn(ExchangeRate $rate) => [
                'pair'       => $rate->pair_label,
                'rate'       => (float) $rate->rate,
                'commission' => (float) $rate->commission_percent,
                'updated_by' => $rate->updater?->username,
                'updated_at' => $rate->updated_at?->format('Y-m-d H:i'),
            ])
            ->toArray();
    }

    // ============================================================
    //  Cache
    // ============================================================

    public function clearCache(string $from, string $to): void
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);

        Cache::forget("exchange_rate_{$from}_{$to}");
    }

    public function clearAllCache(): void
    {
        Cache::flush();
    }
}
