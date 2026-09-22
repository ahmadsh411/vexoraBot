<?php

namespace App\Telegram\Screens\User;

use App\Models\Referral;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserProfileScreen
{
    public static function text(
        User $user,
        ?float $ichancyBalance = null,
        ?string $ichancyCurrency = null,
    ): string {
        $username = htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8');

        $passwordDisplay = self::getPasswordDisplay($user);

        $wallet = $user->wallet;
        $balanceNsp = $wallet ? number_format((float) $wallet->balance_nsp, 2) : '0.00';
        $balanceUsd = $wallet ? number_format((float) $wallet->balance_usd, 2) : '0.00';

        $refStats   = self::getReferralStats($user);
        $wheelStats = self::getWheelStats($user);

        // ✅ سطر رصيد IChancy
        $ichancyLine = self::formatIChancyLine($user, $ichancyBalance, $ichancyCurrency);

        return implode("\n", [
            '👤 <b>معلومات حسابك</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🆔 <b>معرف الحساب:</b> <code>#' . $user->id . '</code>',
            '📛 <b>اسم المستخدم:</b> <code>' . $username . '</code>',
            '🔑 <b>كلمة المرور:</b> ' . $passwordDisplay,
            '',
            '📱 <b>Telegram ID:</b> <code>' . $user->telegram_id . '</code>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💰 <b>محفظة البوت (NSP - ليرة سورية جديدة):</b>',
            '├── 💰 NSP (ليرة سورية جديدة): <b>' . $balanceNsp . '</b>',
            '└── 💵 USD (دولار): <b>' . $balanceUsd . '</b>',
            '',
            $ichancyLine,
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎯 <b>الإحالات:</b>',
            '├── 👥 الكل: <b>' . $refStats['total'] . '</b>',
            '├── 🥇 L1: <b>' . $refStats['l1'] . '</b>',
            '├── 🥈 L2: <b>' . $refStats['l2'] . '</b>',
            '├── 📊 النوع: ' . $refStats['type_label'],
            '└── 💰 المكاسب: <b>' . number_format($refStats['earnings'], 2) . '</b> NSP (ليرة سورية جديدة)',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎡 <b>عجلة الحظ:</b>',
            '├── 🎟 اللفات المتاحة: <b>' . $wheelStats['available'] . '</b>',
            '├── 📅 اليوم: <b>' . $wheelStats['today'] . '</b>',
            '└── 💰 إجمالي المكاسب: <b>' . number_format($wheelStats['won'], 2) . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📅 <b>تاريخ التسجيل:</b> ' . $user->created_at?->format('Y-m-d'),
            '📊 <b>الحالة:</b> ' . $user->status_label,
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '⚠️ <b>تحذير:</b> لا تشارك كلمة المرور مع أي شخص.',
        ]);
    }

    // ============================================================
    //  🎮 سطر رصيد IChancy (يعرض NPS مع NSP)
    // ============================================================
    private static function formatIChancyLine(
        User $user,
        ?float $ichancyBalance,
        ?string $ichancyCurrency,
    ): string {
        if (! $user->ichancyAccount) {
            return implode("\n", [
                '🎮 <b>حساب IChancy (NPS - ليرة سورية قديمة):</b>',
                '└── ⚠️ <i>لم يتم الربط بعد</i>',
                '',
            ]);
        }

        if ($ichancyBalance === null) {
            return implode("\n", [
                '🎮 <b>حساب IChancy (NPS - ليرة سورية قديمة):</b>',
                '└── ⚠️ <i>تعذّر الجلب</i>',
                '🆔 <b>Player ID:</b> <code>' . $user->ichancyAccount->ichancy_player_id . '</code>',
                '',
            ]);
        }

        // ✅ الرصيد في IChancy = NSP
        // العرض = NPS (قديمة) = NSP × display_rate
        $displayRate = (int) Setting::get('ichancy.display_rate', 100);
        $balanceNps  = $ichancyBalance * $displayRate;

        return implode("\n", [
            '🎮 <b>حساب IChancy (NPS - ليرة سورية قديمة):</b>',
            '└── 💵 <b>' . number_format($balanceNps, 2) . ' NPS (ليرة سورية قديمة)</b>',
            '   <i>(يعادل ' . number_format($ichancyBalance, 2) . ' NSP - ليرة سورية جديدة)</i>',
            '',
        ]);
    }

    // ============================================================
    //  🔓 فك تشفير كلمة المرور
    // ============================================================
    private static function getPasswordDisplay(User $user): string
    {
        if (empty($user->password_encrypted)) {
            return '🔒 <i>غير متاحة</i>';
        }

        try {
            $encryptionService = app(\App\Services\PasswordEncryptionService::class);

            $password = $encryptionService->decrypt(
                $user->password_encrypted,
                $user->password_key_id ?? 'v1',
            );

            if ($password === null) {
                return '🔒 <i>تعذّر فك التشفير</i>';
            }

            try {
                \App\Models\SensitiveAccessLog::create([
                    'user_id'       => $user->id,
                    'accessed_by'   => $user->id,
                    'resource_type' => 'password',
                    'resource_id'   => $user->id,
                    'action'        => 'read',
                    'reason'        => 'User viewed own password',
                    'ip_address'    => request()?->ip(),
                    'user_agent'    => substr((string) request()?->userAgent(), 0, 500),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to log password access', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }

            return '<code>' . htmlspecialchars($password, ENT_QUOTES, 'UTF-8') . '</code>';
        } catch (\Throwable $e) {
            Log::error('Failed to decrypt password', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return '🔒 <i>تعذّر فك التشفير</i>';
        }
    }

    private static function getReferralStats(User $user): array
    {
        $referrals = Referral::forReferrer($user->id);

        return [
            'total'      => (clone $referrals)->count(),
            'l1'         => (clone $referrals)->level1()->count(),
            'l2'         => (clone $referrals)->level2()->count(),
            'earnings'   => (float) $user->referral_earnings,
            'type_label' => $user->referral_type_label,
        ];
    }

    private static function getWheelStats(User $user): array
    {
        $state = $user->wheelStates()->first();

        return [
            'available' => $state ? (int) $state->available_spins : 0,
            'today'     => $state ? (int) $state->spins_today : 0,
            'won'       => $state ? (float) $state->total_won_amount : 0.0,
        ];
    }
}
