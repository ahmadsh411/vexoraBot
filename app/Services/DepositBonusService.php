<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class DepositBonusService
{
    public const KEY_ENABLED = 'deposit_bonus.enabled';
    public const KEY_PERCENT = 'deposit_bonus.percent';

    // ═══════════════════════════════════════════════════════════
    //  الاستعلامات
    // ═══════════════════════════════════════════════════════════

    public function isEnabled(): bool
    {
        return (bool) Setting::get(self::KEY_ENABLED, false);
    }

    public function getPercent(): int
    {
        $p = (int) Setting::get(self::KEY_PERCENT, 0);

        return max(0, min(100, $p));
    }

    /**
     * حساب المكافأة لقيمة معينة.
     */
    public function calculate(float $amount): float
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $percent = $this->getPercent();

        if ($percent <= 0 || $amount <= 0) {
            return 0;
        }

        return round(($amount * $percent) / 100, 2);
    }

    // ═══════════════════════════════════════════════════════════
    //  التطبيق
    // ═══════════════════════════════════════════════════════════

    /**
     * تطبيق المكافأة على معاملة إيداع معتمدة.
     *
     * @return array{applied: bool, amount: float, percent: int}
     */
    public function applyOnDeposit(Transaction $transaction): array
    {
        $result = ['applied' => false, 'amount' => 0.0, 'percent' => 0];

        if (! $this->isEnabled()) {
            return $result;
        }

        if (! $transaction->user_id) {
            return $result;
        }

        // فقط معاملات الإيداع المعتمدة
        if (! $this->isDepositTransaction($transaction)) {
            return $result;
        }

        // تجنّب التطبيق المزدوج
        if ($this->alreadyApplied($transaction)) {
            Log::info('Deposit bonus skipped (already applied)', [
                'transaction_id' => $transaction->id,
            ]);
            return $result;
        }

        $user = $transaction->user;

        if (! $user) {
            return $result;
        }

        $amount  = (float) $transaction->amount_to;
        $percent = $this->getPercent();
        $bonus   = $this->calculate($amount);

        if ($bonus <= 0) {
            return $result;
        }

        try {
            // ✅ 1. أضف للمحفظة
            $wallet = $user->wallet;

            if (! $wallet) {
                Log::warning('Deposit bonus: no wallet', ['user_id' => $user->id]);
                return $result;
            }

            $currency = $transaction->to_currency ?: 'NSP';

            $wallet->credit($currency, $bonus);

            // ✅ 2. سجّل العملية
            $this->logBonusTransaction($user, $transaction, $bonus, $currency, $percent);

            $result = ['applied' => true, 'amount' => $bonus, 'percent' => $percent];

            Log::info('Deposit bonus applied', [
                'user_id'        => $user->id,
                'transaction_id' => $transaction->id,
                'deposit'        => $amount,
                'bonus'          => $bonus,
                'percent'        => $percent,
            ]);
        } catch (\Throwable $e) {
            Log::error('Deposit bonus failed', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════

    private function isDepositTransaction(Transaction $t): bool
    {
        return in_array($t->type, [
            Transaction::TYPE_DEPOSIT,
            Transaction::TYPE_DEPOSIT_USD,
        ], true);
    }

    private function alreadyApplied(Transaction $transaction): bool
    {
        return Transaction::where('user_id', $transaction->user_id)
            ->where('type', Transaction::TYPE_DEPOSIT_BONUS)
            ->where('reference', 'bonus_' . $transaction->id)
            ->exists();
    }

    private function logBonusTransaction(
        User $user,
        Transaction $source,
        float $bonus,
        string $currency,
        int $percent,
    ): void {
        try {
            Transaction::create([
                'user_id'       => $user->id,
                'to_wallet_id'  => $user->wallet?->id,
                'type'          => Transaction::TYPE_DEPOSIT_BONUS,
                'from_currency' => $currency,
                'to_currency'   => $currency,
                'amount_from'   => $bonus,
                'amount_to'     => $bonus,
                'status'        => Transaction::STATUS_COMPLETED,
                'reference'     => 'bonus_' . $source->id,
                'notes'         => "مكافأة إيداع {$percent}% — مرتبطة بالعملية #{$source->id}",
                'completed_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log bonus transaction', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
