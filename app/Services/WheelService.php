<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wheel;
use App\Models\WheelPrize;
use App\Models\WheelSpin;
use App\Models\WheelUserState;
use App\Services\Concerns\HasLockingHelpers;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class WheelService
{
    use HasLockingHelpers;

    // ============================================================
    //  الحالة
    // ============================================================

    /**
     * هل العجلة مفعّلة؟
     */
    public function isEnabled(): bool
    {
        return Wheel::active()->exists();
    }

    /**
     * العجلة النشطة.
     */
    public function getActiveWheel(): ?Wheel
    {
        return Wheel::active()->first();
    }

    /**
     * حالة المستخدم.
     */
    public function getState(User $user): ?array
    {
        $wheel = $this->getActiveWheel();

        if (! $wheel) {
            return null;
        }

        $state = WheelUserState::getOrCreateFor($user->id, $wheel->id);
        $state->resetDailyIfNeeded();

        return [
            'wheel'            => $wheel,
            'available_spins'  => (int) $state->available_spins,
            'total_spins_used' => (int) $state->total_spins_used,
            'total_won_amount' => (float) $state->total_won_amount,
            'spins_today'      => (int) $state->spins_today,
            'daily_limit'      => (int) $wheel->daily_limit,
            'remaining_today'  => $state->getRemainingToday($wheel),
            'can_spin'         => $state->canSpin($wheel),
        ];
    }

    // ============================================================
    //  تنفيذ لفة
    // ============================================================

    /**
     * تنفيذ لفة واحدة.
     *
     * @return array{spin: WheelSpin, prize: WheelPrize, state: WheelUserState}
     */
    public function spin(User $user): array
    {
        return $this->runInTransaction(function () use ($user) {
            $wheel = $this->getActiveWheel();

            if (! $wheel) {
                throw new \RuntimeException('العجلة غير مفعّلة حالياً.');
            }

            // ✅ قفل حالة المستخدم
            $state = WheelUserState::getOrCreateFor($user->id, $wheel->id);
            $state = WheelUserState::query()
                ->whereKey($state->id)
                ->lockForUpdate()
                ->firstOrFail();

            // ✅ تصفير يومي
            $state->resetDailyIfNeeded();
            $state->refresh();

            // ✅ تحقق
            if ($state->available_spins <= 0) {
                throw new \RuntimeException('لا توجد لفات متاحة.');
            }

            if ($state->spins_today >= $wheel->daily_limit) {
                throw new \RuntimeException('وصلت الحد اليومي.');
            }

            // ✅ اختيار الجائزة
            $prize = $this->pickPrize($wheel);

            if (! $prize) {
                throw new \RuntimeException('لا توجد جوائز متاحة.');
            }

            // ✅ خصم لفة
            $state->consumeSpin();

            // ✅ معالجة الجائزة
            $wonValue = (float) $prize->value;
            $currency = $prize->currency;

            $spin = WheelSpin::create([
                'user_id'   => $user->id,
                'wheel_id'  => $wheel->id,
                'prize_id'  => $prize->id,
                'won_value' => $prize->isBalance() ? $wonValue : 0,
                'currency'  => $currency,
                'source'    => WheelSpin::SOURCE_MANUAL,
                'metadata'  => [
                    'prize_type' => $prize->type,
                    'prize_name' => $prize->name,
                ],
            ]);

            // ✅ إضافة الرصيد (فقط لنوع balance)
            if ($prize->givesReward()) {
                $wallet = app(WalletService::class)->getUserWallet($user);
                $wallet = \App\Models\Wallet::query()
                    ->whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $wallet->credit($currency, $wonValue);
                $state->addWonAmount($wonValue);

                // ✅ إشعار قناة Transactions
                $this->notifyTransactionsChannelAboutWin($user, $prize, $wonValue, $currency);
            }

            // ✅ إعادة تدوير (استرداد لفة)
            if ($prize->isRecycle()) {
                $state->addSpins(1, $wheel->max_stored_spins);
            }

            $this->logFinancialSuccess('wheel.spin', [
                'user_id'  => $user->id,
                'prize_id' => $prize->id,
                'won_value' => $wonValue,
                'type'     => $prize->type,
            ]);

            return [
                'spin'  => $spin->fresh(),
                'prize' => $prize,
                'state' => $state->fresh(),
            ];
        });
    }

    // ============================================================
    //  📢 إشعار قناة Transactions — الفوز بجائزة
    // ============================================================

    private function notifyTransactionsChannelAboutWin(
        User $user,
        WheelPrize $prize,
        float $wonValue,
        string $currency,
    ): void {
        try {
            $bot = app(Nutgram::class);
            $notificationService = app(NotificationService::class);

            $text = implode("\n", [
                '🎡 <b>فوز بجائزة عجلة الحظ</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👤 <b>المستخدم:</b>',
                '└── <code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
                '└── 🆔 <code>#' . $user->id . '</code>',
                '',
                '🎁 <b>الجائزة:</b> ' . htmlspecialchars($prize->name, ENT_QUOTES, 'UTF-8'),
                '💰 <b>القيمة:</b> <b>' . number_format($wonValue, 2) . ' ' . $currency . '</b>',
                '',
                '✅ تمت إضافة المبلغ إلى رصيد المستخدم.',
                '',
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            $notificationService->notifyTransactionsChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about wheel win', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  منح لفات
    // ============================================================

    /**
     * منح لفات من إيداع.
     */
    public function awardDepositSpin(User $user, float $amount, string $currency): array
    {
        return $this->runInTransaction(function () use ($user, $amount, $currency) {
            $wheel = $this->getActiveWheel();

            if (! $wheel || ! $wheel->auto_grant_on_deposit) {
                return ['awarded' => false];
            }

            $spinsToAdd = $wheel->calculateSpinsFromDeposit($amount, $currency);

            if ($spinsToAdd <= 0) {
                return ['awarded' => false];
            }

            // ✅ قفل حالة المستخدم
            $state = WheelUserState::getOrCreateFor($user->id, $wheel->id);
            $state = WheelUserState::query()
                ->whereKey($state->id)
                ->lockForUpdate()
                ->firstOrFail();

            $added = $state->addSpins($spinsToAdd, $wheel->max_stored_spins);

            if ($added <= 0) {
                return ['awarded' => false];
            }

            // ✅ سجل الحدث
            WheelSpin::create([
                'user_id'   => $user->id,
                'wheel_id'  => $wheel->id,
                'prize_id'  => null,
                'won_value' => 0,
                'currency'  => $currency,
                'source'    => $currency === 'USD'
                    ? WheelSpin::SOURCE_DEPOSIT_USD
                    : WheelSpin::SOURCE_DEPOSIT_SYP,
                'metadata'  => [
                    'event'        => 'awarded',
                    'spins_gained' => $added,
                    'amount'       => $amount,
                ],
            ]);

            $state->refresh();

            return [
                'awarded'      => true,
                'spins_gained' => $added,
                'total_spins'  => (int) $state->available_spins,
            ];
        });
    }

    /**
     * منح لفات من إحالة.
     */
    public function awardReferralSpin(User $user, int $referralCount): array
    {
        return $this->runInTransaction(function () use ($user, $referralCount) {
            $wheel = $this->getActiveWheel();

            if (! $wheel) {
                return ['awarded' => false];
            }

            $spinsToAdd = $wheel->calculateSpinsFromReferrals($referralCount);

            if ($spinsToAdd <= 0) {
                return ['awarded' => false];
            }

            $state = WheelUserState::getOrCreateFor($user->id, $wheel->id);
            $state = WheelUserState::query()
                ->whereKey($state->id)
                ->lockForUpdate()
                ->firstOrFail();

            $added = $state->addSpins($spinsToAdd, $wheel->max_stored_spins);

            if ($added <= 0) {
                return ['awarded' => false];
            }

            WheelSpin::create([
                'user_id'   => $user->id,
                'wheel_id'  => $wheel->id,
                'won_value' => 0,
                'currency'  => 'SYP',
                'source'    => WheelSpin::SOURCE_REFERRAL,
                'metadata'  => [
                    'event'        => 'awarded',
                    'spins_gained' => $added,
                    'referrals'    => $referralCount,
                ],
            ]);

            $state->refresh();

            return [
                'awarded'      => true,
                'spins_gained' => $added,
                'total_spins'  => (int) $state->available_spins,
            ];
        });
    }

    /**
     * منح يدوي من الأدمن.
     */
    public function grantSpins(User $user, int $count): int
    {
        return $this->runInTransaction(function () use ($user, $count) {
            $wheel = $this->getActiveWheel();

            if (! $wheel) {
                return 0;
            }

            $state = WheelUserState::getOrCreateFor($user->id, $wheel->id);
            $state = WheelUserState::query()
                ->whereKey($state->id)
                ->lockForUpdate()
                ->firstOrFail();

            $added = $state->addSpins($count, $wheel->max_stored_spins);

            if ($added > 0) {
                WheelSpin::create([
                    'user_id'   => $user->id,
                    'wheel_id'  => $wheel->id,
                    'won_value' => 0,
                    'currency'  => 'SYP',
                    'source'    => WheelSpin::SOURCE_ADMIN,
                    'metadata'  => [
                        'event'        => 'granted',
                        'spins_gained' => $added,
                    ],
                ]);
            }

            return $added;
        });
    }

    /**
     * تصفير العداد اليومي.
     */
    public function resetDaily(User $user): bool
    {
        return $this->runInTransaction(function () use ($user) {
            $wheel = $this->getActiveWheel();

            if (! $wheel) {
                return false;
            }

            $state = WheelUserState::getOrCreateFor($user->id, $wheel->id);

            $state->update([
                'spins_today'    => 0,
                'last_spin_date' => null,
            ]);

            return true;
        });
    }

    // ============================================================
    //  اختيار الجائزة
    // ============================================================

    private function pickPrize(Wheel $wheel): ?WheelPrize
    {
        $prizes = $wheel->activePrizes()->get();

        if ($prizes->isEmpty()) {
            return null;
        }

        $totalWeight = (int) $prizes->sum('weight');

        if ($totalWeight <= 0) {
            return null;
        }

        $random = random_int(1, $totalWeight);
        $cumulative = 0;

        foreach ($prizes as $prize) {
            $cumulative += (int) $prize->weight;

            if ($random <= $cumulative) {
                return $prize;
            }
        }

        return $prizes->last();
    }

    // ============================================================
    //  إحصائيات
    // ============================================================

    public function getStats(): array
    {
        return [
            'total_spins' => WheelSpin::count(),
            'today_spins' => WheelSpin::today()->count(),
            'total_won'   => (float) WheelSpin::sum('won_value'),
            'today_won'   => (float) WheelSpin::today()->sum('won_value'),
        ];
    }
}
