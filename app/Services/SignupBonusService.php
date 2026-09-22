<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Log;

class SignupBonusService
{
    public const KEY_ENABLED    = 'signup_bonus.enabled';
    public const KEY_AMOUNT_NSP = 'signup_bonus.amount_nsp';
    public const KEY_AMOUNT_USD = 'signup_bonus.amount_usd';

    // ═══════════════════════════════════════════════════════════
    //  الاستعلامات
    // ═══════════════════════════════════════════════════════════

    public function isEnabled(): bool
    {
        return (bool) Setting::get(self::KEY_ENABLED, false);
    }

    public function getAmountNsp(): float
    {
        return max(0, (float) Setting::get(self::KEY_AMOUNT_NSP, 0));
    }

    public function getAmountUsd(): float
    {
        return max(0, (float) Setting::get(self::KEY_AMOUNT_USD, 0));
    }

    // ═══════════════════════════════════════════════════════════
    //  التطبيق
    // ═══════════════════════════════════════════════════════════

    /**
     * تطبيق مكافأة التسجيل على مستخدم جديد.
     *
     * @return array{applied: bool, nsp: float, usd: float}
     */
    public function apply(User $user): array
    {
        $result = ['applied' => false, 'nsp' => 0.0, 'usd' => 0.0];

        if (! $this->isEnabled()) {
            return $result;
        }

        // تحقق من عدم التطبيق المسبق
        if ($this->alreadyApplied($user)) {
            Log::info('Signup bonus skipped (already applied)', [
                'user_id' => $user->id,
            ]);
            return $result;
        }

        $amountNsp = $this->getAmountNsp();
        $amountUsd = $this->getAmountUsd();

        if ($amountNsp <= 0 && $amountUsd <= 0) {
            Log::info('Signup bonus skipped (zero amounts)', [
                'user_id' => $user->id,
            ]);
            return $result;
        }

        try {
            // احصل على المحفظة أو أنشئها
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id, 'type' => Wallet::TYPE_USER],
                [
                    'balance_nsp' => 0,
                    'balance_usd' => 0,
                    'is_active'   => true,
                    'is_frozen'   => false,
                ]
            );

            // ✅ أضف NSP
            if ($amountNsp > 0) {
                $wallet->credit('NSP', $amountNsp);
            }

            // ✅ أضف USD
            if ($amountUsd > 0) {
                $wallet->credit('USD', $amountUsd);
            }

            // سجّل العملية
            $this->logBonusTransaction($user, $wallet, $amountNsp, $amountUsd);

            $result = [
                'applied' => true,
                'nsp'     => $amountNsp,
                'usd'     => $amountUsd,
            ];

            Log::info('Signup bonus applied', [
                'user_id' => $user->id,
                'nsp'     => $amountNsp,
                'usd'     => $amountUsd,
            ]);
        } catch (\Throwable $e) {
            Log::error('Signup bonus failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════

    private function alreadyApplied(User $user): bool
    {
        return Transaction::where('user_id', $user->id)
            ->where('type', 'signup_bonus')
            ->exists();
    }

    private function logBonusTransaction(
        User $user,
        Wallet $wallet,
        float $amountNsp,
        float $amountUsd,
    ): void {
        try {
            // NSP
            if ($amountNsp > 0) {
                Transaction::create([
                    'user_id'       => $user->id,
                    'to_wallet_id'  => $wallet->id,
                    'type'          => 'signup_bonus',
                    'from_currency' => 'NSP',
                    'to_currency'   => 'NSP',
                    'amount_from'   => $amountNsp,
                    'amount_to'     => $amountNsp,
                    'status'        => Transaction::STATUS_COMPLETED,
                    'reference'     => 'signup_bonus_nsp_' . $user->id,
                    'notes'         => 'مكافأة تسجيل (NSP)',
                    'completed_at'  => now(),
                ]);
            }

            // USD
            if ($amountUsd > 0) {
                Transaction::create([
                    'user_id'       => $user->id,
                    'to_wallet_id'  => $wallet->id,
                    'type'          => 'signup_bonus',
                    'from_currency' => 'USD',
                    'to_currency'   => 'USD',
                    'amount_from'   => $amountUsd,
                    'amount_to'     => $amountUsd,
                    'status'        => Transaction::STATUS_COMPLETED,
                    'reference'     => 'signup_bonus_usd_' . $user->id,
                    'notes'         => 'مكافأة تسجيل (USD)',
                    'completed_at'  => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to log signup bonus transaction', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
