<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\ReferralSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Concerns\HasLockingHelpers;

class ReferralRewardService
{
    use HasLockingHelpers;

    // ============================================================
    //  مكافآت فورية
    // ============================================================

    /**
     * منح مكافأة فورية (Level محدد).
     */
    public function rewardInstant(
        Transaction $transaction,
        User $depositor,
        float $amount,
        int $level,
        ReferralSetting $settings,
    ): ?ReferralReward {
        $referrerId = match ($level) {
            Referral::LEVEL_1 => $depositor->referred_by,
            Referral::LEVEL_2 => $depositor->referred_by_level_2,
            default           => null,
        };

        if (! $referrerId) {
            return null;
        }

        $referrer = User::find($referrerId);

        if (! $referrer) {
            return null;
        }

        // ⚠️ فقط للفوري
        if ($referrer->referral_type !== Referral::TYPE_INSTANT) {
            return null;
        }

        $percent = $settings->getInstantPercent($level);
        $reward  = round($amount * ($percent / 100), 2);

        if ($reward <= 0) {
            return null;
        }

        return $this->payReward(
            referrer: $referrer,
            referred: $depositor,
            transaction: $transaction,
            level: $level,
            type: Referral::TYPE_INSTANT,
            basis: ReferralReward::BASIS_DEPOSIT,
            amount: $reward,
            currency: $transaction->to_currency ?? 'SYP',
            percent: $percent,
            notes: "مكافأة فورية L{$level}",
        );
    }

    // ============================================================
    //  مكافآت دورية
    // ============================================================

    /**
     * منح مكافأة دورة.
     */
    public function rewardCycle(
        User $referrer,
        int $cycleId,
        float $burnL1,
        float $burnL2,
        float $percentL1,
        float $percentL2,
        string $currency = 'SYP',
    ): ?ReferralReward {
        $rewardL1 = round($burnL1 * ($percentL1 / 100), 2);
        $rewardL2 = round($burnL2 * ($percentL2 / 100), 2);
        $total    = $rewardL1 + $rewardL2;

        if ($total <= 0) {
            return null;
        }

        return $this->payReward(
            referrer: $referrer,
            referred: null,
            transaction: null,
            level: Referral::LEVEL_1,
            type: Referral::TYPE_CYCLE,
            basis: ReferralReward::BASIS_BURN,
            amount: $total,
            currency: $currency,
            percent: $percentL1,
            notes: "مكافأة دورة #{$cycleId}",
            cycleId: $cycleId,
        );
    }

    // ============================================================
    //  الدفع (Private)
    // ============================================================

    private function payReward(
        User $referrer,
        ?User $referred,
        ?Transaction $transaction,
        int $level,
        string $type,
        string $basis,
        float $amount,
        string $currency,
        float $percent,
        ?string $notes = null,
        ?int $cycleId = null,
    ): ReferralReward {
        return $this->runInTransaction(function () use (
            $referrer,
            $referred,
            $transaction,
            $level,
            $type,
            $basis,
            $amount,
            $currency,
            $percent,
            $notes,
            $cycleId
        ) {
            // ✅ 1. سجّل المكافأة
            $reward = ReferralReward::create([
                'referrer_id'        => $referrer->id,
                'referred_id'        => $referred?->id,
                'transaction_id'     => $transaction?->id,
                'level'              => $level,
                'type'               => $type,
                'basis'              => $basis,
                'amount'             => $amount,
                'currency'           => $currency,
                'commission_percent' => $percent,
                'status'             => ReferralReward::STATUS_PAID,
                'notes'              => $notes,
                'metadata'           => ['cycle_id' => $cycleId],
                'paid_at'            => now(),
            ]);

            // ✅ 2. أضف للمحفظة (مع قفل)
            $wallet = Wallet::query()
                ->where('user_id', $referrer->id)
                ->where('type', Wallet::TYPE_USER)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $wallet = Wallet::create([
                    'user_id'            => $referrer->id,
                    'type'               => Wallet::TYPE_USER,
                    'balance_nsp'        => 0,
                    'balance_usd'        => 0,
                    'total_deposit_nsp'  => 0,
                    'total_deposit_usd'  => 0,
                    'total_withdraw_nsp' => 0,
                    'total_withdraw_usd' => 0,
                    'is_active'          => true,
                    'is_frozen'          => false,
                ]);
            }

            $wallet->credit($currency, $amount);

            // ✅ 3. حدّث إجمالي مكاسب المُحيل
            User::whereKey($referrer->id)->lockForUpdate()->increment('referral_earnings', $amount);

            // ✅ 4. حدّث سجل الإحالة (إن وُجد)
            if ($referred) {
                $referral = Referral::where('referrer_id', $referrer->id)
                    ->where('referred_id', $referred->id)
                    ->where('level', $level)
                    ->first();

                if ($referral) {
                    $referral->addEarning($amount);
                }
            }

            $this->logFinancialSuccess('referral.reward_paid', [
                'referrer_id' => $referrer->id,
                'referred_id' => $referred?->id,
                'level'       => $level,
                'type'        => $type,
                'basis'       => $basis,
                'amount'      => $amount,
                'percent'     => $percent,
            ]);

            return $reward;
        });
    }
}
