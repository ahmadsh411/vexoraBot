<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\ReferralCycle;
use App\Models\ReferralCycleReward;
use App\Models\ReferralSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Concerns\HasLockingHelpers;
use Illuminate\Support\Collection;

class ReferralCycleService
{
    use HasLockingHelpers;

    public function __construct(
        private readonly ReferralRewardService $rewardService,
    ) {}

    // ============================================================
    //  الحصول على الدورة الحالية
    // ============================================================

    public function getOrCreateCurrentCycle(): ReferralCycle
    {
        return $this->runInTransaction(function () {
            $cycle = ReferralCycle::open()->latestFirst()->first();

            if ($cycle) {
                return $cycle;
            }

            $settings = ReferralSetting::current();
            $days = max(1, (int) $settings->cycle_days);

            $startDate = now()->startOfDay();
            $endDate   = $startDate->copy()->addDays($days - 1)->endOfDay();

            $existing = ReferralCycle::whereDate('start_date', $startDate->toDateString())
                ->whereDate('end_date', $endDate->toDateString())
                ->first();

            if ($existing) {
                if ($existing->status !== ReferralCycle::STATUS_OPEN) {
                    $existing->update(['status' => ReferralCycle::STATUS_OPEN]);
                }

                return $existing->fresh();
            }

            return ReferralCycle::create([
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'status'     => ReferralCycle::STATUS_OPEN,
            ]);
        });
    }

    public function getCurrentCycle(): ?ReferralCycle
    {
        return ReferralCycle::open()->latestFirst()->first();
    }

    // ============================================================
    //  معالجة الدورة
    // ============================================================

    /**
     * معالجة دورة (إغلاق + منح مكافآت).
     */
    public function processCycle(ReferralCycle $cycle): array
    {
        if (! $cycle->isOpen()) {
            throw new \RuntimeException('الدورة مغلقة أو قيد المعالجة.');
        }

        return $this->runInTransaction(function () use ($cycle) {
            $cycle = ReferralCycle::query()
                ->whereKey($cycle->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $cycle->isOpen()) {
                throw new \RuntimeException('الدورة مغلقة بالفعل.');
            }

            $cycle->markProcessing();

            $settings = ReferralSetting::current();

            // ✅ جمع إحصائيات الحرق
            $l1Stats = $this->gatherL1BurnStats($cycle);
            $l2Stats = $this->gatherL2BurnStats($cycle);

            $allReferrerIds = $l1Stats->keys()->merge($l2Stats->keys())->unique();

            $totalRewards = 0;
            $referrersProcessed = 0;

            foreach ($allReferrerIds as $referrerId) {
                $result = $this->createCycleReward(
                    cycle: $cycle,
                    referrerId: (int) $referrerId,
                    l1Stats: $l1Stats->get($referrerId, []),
                    l2Stats: $l2Stats->get($referrerId, []),
                    settings: $settings,
                );

                if ($result) {
                    $totalRewards += (float) $result->total_reward;
                    $referrersProcessed++;
                }
            }

            $cycle->update([
                'status'          => ReferralCycle::STATUS_CLOSED,
                'total_burned'    => $l1Stats->sum('burn') + $l2Stats->sum('burn'),
                'total_rewards'   => $totalRewards,
                'referrers_count' => $referrersProcessed,
                'referred_count'  => $l1Stats->sum('count') + $l2Stats->sum('count'),
                'processed_at'    => now(),
            ]);

            $this->logFinancialSuccess('referral.cycle_processed', [
                'cycle_id'      => $cycle->id,
                'total_rewards' => $totalRewards,
                'referrers'     => $referrersProcessed,
            ]);

            return [
                'total_rewards'   => $totalRewards,
                'referrers_count' => $referrersProcessed,
            ];
        });
    }

    // ============================================================
    //  التوقعات
    // ============================================================

    public function getExpectedCycleReward(User $user): array
    {
        $settings = ReferralSetting::current();

        $burnL1 = (float) Referral::forReferrer($user->id)
            ->level1()
            ->sum('total_burned');

        $burnL2 = (float) Referral::forReferrer($user->id)
            ->level2()
            ->sum('total_burned');

        $rewardL1 = round($burnL1 * ((float) $settings->cycle_level_1_percent / 100), 2);
        $rewardL2 = round($burnL2 * ((float) $settings->cycle_level_2_percent / 100), 2);

        return [
            'burn_l1' => $burnL1,
            'burn_l2' => $burnL2,
            'reward'  => $rewardL1 + $rewardL2,
        ];
    }

    // ============================================================
    //  Private — Gathering
    // ============================================================

    private function gatherL1BurnStats(ReferralCycle $cycle): Collection
    {
        $startDate = $cycle->start_date->copy()->startOfDay();
        $endDate   = $cycle->end_date->copy()->endOfDay();

        $deposits = Transaction::completed()
            ->deposits()
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->with('user:id,referred_by')
            ->get();

        $grouped = $deposits
            ->filter(fn($tx) => $tx->user?->referred_by !== null)
            ->groupBy(fn($tx) => $tx->user->referred_by);

        $result = collect();

        foreach ($grouped as $referrerId => $txs) {
            $userIds = $txs->pluck('user_id')->unique();
            $totalBurn = 0;

            foreach ($userIds as $userId) {
                $totalBurn += $this->calculateUserBurn((int) $userId, $cycle);
            }

            if ($totalBurn > 0) {
                $result->put($referrerId, [
                    'burn'  => $totalBurn,
                    'count' => $userIds->count(),
                ]);
            }
        }

        return $result;
    }

    private function gatherL2BurnStats(ReferralCycle $cycle): Collection
    {
        $startDate = $cycle->start_date->copy()->startOfDay();
        $endDate   = $cycle->end_date->copy()->endOfDay();

        $deposits = Transaction::completed()
            ->deposits()
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->with('user:id,referred_by_level_2')
            ->get();

        $grouped = $deposits
            ->filter(fn($tx) => $tx->user?->referred_by_level_2 !== null)
            ->groupBy(fn($tx) => $tx->user->referred_by_level_2);

        $result = collect();

        foreach ($grouped as $referrerId => $txs) {
            $userIds = $txs->pluck('user_id')->unique();
            $totalBurn = 0;

            foreach ($userIds as $userId) {
                $totalBurn += $this->calculateUserBurn((int) $userId, $cycle);
            }

            if ($totalBurn > 0) {
                $result->put($referrerId, [
                    'burn'  => $totalBurn,
                    'count' => $userIds->count(),
                ]);
            }
        }

        return $result;
    }

    private function calculateUserBurn(int $userId, ReferralCycle $cycle): float
    {
        $startDate = $cycle->start_date->copy()->startOfDay();
        $endDate   = $cycle->end_date->copy()->endOfDay();

        $deposits = (float) Transaction::completed()
            ->deposits()
            ->where('user_id', $userId)
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->sum('amount_to');

        $withdrawals = (float) Transaction::completed()
            ->withdrawals()
            ->where('user_id', $userId)
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->sum('amount_from');

        return max(0, $deposits - $withdrawals);
    }

    private function createCycleReward(
        ReferralCycle $cycle,
        int $referrerId,
        array $l1Stats,
        array $l2Stats,
        ReferralSetting $settings,
    ): ?ReferralCycleReward {
        $referrer = User::find($referrerId);

        if (! $referrer) {
            return null;
        }

        if ($referrer->referral_type !== Referral::TYPE_CYCLE) {
            return null;
        }

        $burnL1 = (float) ($l1Stats['burn'] ?? 0);
        $burnL2 = (float) ($l2Stats['burn'] ?? 0);

        if ($burnL1 <= 0 && $burnL2 <= 0) {
            return null;
        }

        $percentL1 = (float) $settings->cycle_level_1_percent;
        $percentL2 = (float) $settings->cycle_level_2_percent;

        $rewardL1    = round($burnL1 * ($percentL1 / 100), 2);
        $rewardL2    = round($burnL2 * ($percentL2 / 100), 2);
        $totalReward = $rewardL1 + $rewardL2;

        if ($totalReward <= 0) {
            return null;
        }

        // ✅ سجل مكافأة الدورة
        $cycleReward = ReferralCycleReward::create([
            'cycle_id'          => $cycle->id,
            'referrer_id'       => $referrer->id,
            'total_burned_l1'   => $burnL1,
            'total_burned_l2'   => $burnL2,
            'reward_l1'         => $rewardL1,
            'reward_l2'         => $rewardL2,
            'total_reward'      => $totalReward,
            'currency'          => 'SYP',
            'percent_l1'        => $percentL1,
            'percent_l2'        => $percentL2,
            'referred_count_l1' => $l1Stats['count'] ?? 0,
            'referred_count_l2' => $l2Stats['count'] ?? 0,
            'status'            => ReferralCycleReward::STATUS_PAID,
            'paid_at'           => now(),
        ]);

        // ✅ استخدم ReferralRewardService للدفع
        $this->rewardService->rewardCycle(
            referrer: $referrer,
            cycleId: $cycle->id,
            burnL1: $burnL1,
            burnL2: $burnL2,
            percentL1: $percentL1,
            percentL2: $percentL2,
            currency: 'SYP',
        );

        return $cycleReward;
    }
}
