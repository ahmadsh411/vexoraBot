<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\ReferralSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Concerns\HasLockingHelpers;
use Illuminate\Support\Str;

class ReferralService
{
    use HasLockingHelpers;

    public function __construct(
        private readonly ReferralRewardService $rewardService,
        private readonly WalletService $walletService,
    ) {}

    // ============================================================
    //  كود الإحالة
    // ============================================================

    public function generateReferralCode(): string
    {
        do {
            $code = 'VX' . strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    public function getReferralLink(User $user): ?string
    {
        if (! $user->referral_code) {
            return null;
        }

        $botUsername = config('services.telegram.bot_username', 'VexoraBot');

        return "https://t.me/{$botUsername}?start=ref_{$user->referral_code}";
    }

    // ============================================================
    //  اختيار النوع
    // ============================================================

    public function chooseReferralType(User $user, string $type): bool
    {
        if (! in_array($type, [Referral::TYPE_INSTANT, Referral::TYPE_CYCLE], true)) {
            return false;
        }

        if ($user->hasChosenReferralType()) {
            return false;
        }

        $code = $user->referral_code ?? $this->generateReferralCode();

        $user->update([
            'referral_code'      => $code,
            'referral_type'      => $type,
            'referral_chosen_at' => now(),
        ]);

        return true;
    }

    // ============================================================
    //  ربط المستخدم الجديد بمُحيل
    // ============================================================

    /**
     * ربط مستخدم جديد بمُحيل.
     */
    public function attachReferrer(User $newUser, string $referralCode): bool
    {
        $referrer = User::where('referral_code', $referralCode)->first();

        if (! $referrer) {
            return false;
        }

        if ($referrer->id === $newUser->id) {
            return false;
        }

        if ($newUser->referred_by) {
            return false;
        }

        if (! $referrer->referral_type) {
            return false;
        }

        return $this->runInTransaction(function () use ($newUser, $referrer) {
            // ✅ ربط L1
            $newUser->update(['referred_by' => $referrer->id]);

            // ✅ ربط L2
            if ($referrer->referred_by) {
                $newUser->update(['referred_by_level_2' => $referrer->referred_by]);
            }

            // ✅ سجل L1
            Referral::updateOrCreate(
                [
                    'referrer_id' => $referrer->id,
                    'referred_id' => $newUser->id,
                    'level'       => Referral::LEVEL_1,
                ],
                [
                    'type'   => $referrer->referral_type,
                    'status' => Referral::STATUS_ACTIVE,
                ],
            );

            // ✅ سجل L2
            if ($referrer->referred_by) {
                $level2Referrer = User::find($referrer->referred_by);

                if ($level2Referrer) {
                    Referral::updateOrCreate(
                        [
                            'referrer_id' => $level2Referrer->id,
                            'referred_id' => $newUser->id,
                            'level'       => Referral::LEVEL_2,
                        ],
                        [
                            'type'   => $level2Referrer->referral_type ?? Referral::TYPE_INSTANT,
                            'status' => Referral::STATUS_ACTIVE,
                        ],
                    );

                    $level2Referrer->increment('referrals_count');
                }
            }

            $referrer->increment('referrals_count');

            // ✅ منح لفة عجلة
            try {
                $wheelResult = app(WheelService::class)->awardReferralSpin($referrer, 1);

                if ($wheelResult['awarded'] ?? false) {
                    $this->notifyReferrerAboutWheelSpin($referrer, $wheelResult);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Wheel award referral failed', [
                    'referrer_id' => $referrer->id,
                    'error'       => $e->getMessage(),
                ]);
            }

            return true;
        });
    }

    // ============================================================
    //  معالجة الإيداع (مكافآت فورية)
    // ============================================================

    public function processDeposit(Transaction $transaction): void
    {
        $depositor = $transaction->user;

        if (! $depositor || ! $depositor->referred_by) {
            return;
        }

        $amount = (float) $transaction->amount_to;

        if ($amount <= 0) {
            return;
        }

        $settings = ReferralSetting::current();

        if (! $settings->is_active) {
            return;
        }

        $this->runInTransaction(function () use ($transaction, $depositor, $amount, $settings) {
            // ✅ تحديث إحصائيات المُحال
            $this->updateReferralStats($depositor, $amount);

            // ✅ L1
            $this->rewardService->rewardInstant($transaction, $depositor, $amount, Referral::LEVEL_1, $settings);

            // ✅ L2
            $this->rewardService->rewardInstant($transaction, $depositor, $amount, Referral::LEVEL_2, $settings);
        });
    }

    // ============================================================
    //  إحصائيات
    // ============================================================

    public function getStats(User $user): array
    {
        $referrals = Referral::forReferrer($user->id);

        return [
            'total_referrals' => (clone $referrals)->count(),
            'level_1'         => (clone $referrals)->level1()->count(),
            'level_2'         => (clone $referrals)->level2()->count(),
            'active'          => (clone $referrals)->active()->count(),
            'total_earned'    => (float) $user->referral_earnings,
            'total_deposited' => (float) (clone $referrals)->sum('total_deposited'),
            'total_burned'    => (float) (clone $referrals)->sum('total_burned'),
        ];
    }

    // ============================================================
    //  Private
    // ============================================================

    private function updateReferralStats(User $depositor, float $amount): void
    {
        foreach (
            [
                Referral::LEVEL_1 => $depositor->referred_by,
                Referral::LEVEL_2 => $depositor->referred_by_level_2,
            ] as $level => $referrerId
        ) {
            if (! $referrerId) {
                continue;
            }

            $referral = Referral::where('referrer_id', $referrerId)
                ->where('referred_id', $depositor->id)
                ->where('level', $level)
                ->first();

            if ($referral) {
                $referral->addDeposit($amount);
            }
        }
    }

    private function notifyReferrerAboutWheelSpin(User $referrer, array $result): void
    {
        if (! $referrer->telegram_id) {
            return;
        }

        try {
            $spinsGained = $result['spins_gained'] ?? 0;
            $totalSpins  = $result['total_spins'] ?? 0;

            $text = implode("\n", [
                '🎉 <b>مبروك!</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👥 وصلت لعدد إحالات جديد!',
                '',
                '🎡 حصلت على <b>' . $spinsGained . '</b> لفة عجلة حظ!',
                '',
                '💰 <b>اللفات المتاحة:</b> <b>' . $totalSpins . '</b>',
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🎁 اذهب إلى 🎡 عجلة الحظ ولف العجلة!',
            ]);

            app(\SergiX44\Nutgram\Nutgram::class)->sendMessage(
                text: $text,
                chat_id: $referrer->telegram_id,
                parse_mode: 'HTML',
                reply_markup: \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup::make()
                    ->addRow(
                        \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                            text: '🎡 افتح العجلة',
                            callback_data: 'user.wheel',
                        ),
                    ),
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Referral wheel notify failed', [
                'user_id' => $referrer->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
