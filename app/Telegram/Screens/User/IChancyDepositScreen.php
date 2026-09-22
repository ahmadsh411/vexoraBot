<?php

namespace App\Telegram\Screens\User;

use App\Models\Setting;
use App\Models\User;

class IChancyDepositScreen
{
    public static function options(User $user): string
    {
        $cfg = self::cfg();
        $balance = self::walletBalance($user);

        return implode("\n", [
            '🎮 <b>شحن حساب IChancy</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💰 <b>محفظة البوت (NSP - ليرة سورية جديدة):</b>',
            '└── <code>' . number_format($balance, 2) . ' NSP (ليرة سورية جديدة)</code>',
            '',
            '💡 <i>عند الشحن، سيصل لحسابك في IChancy</i>',
            '💡 <i>بمضاعف ×100 كـ NPS (ليرة سورية قديمة)</i>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الحدود:</b>',
            '├── الحد الأدنى: <b>' . number_format($cfg['min_deposit']) . ' NSP (ليرة سورية جديدة)</b>',
            '└── الحد الأقصى: <b>' . number_format($cfg['max_deposit']) . ' NSP (ليرة سورية جديدة)</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            'اختر طريقة الشحن:',
        ]);
    }

    public static function askAmount(User $user): string
    {
        $cfg = self::cfg();
        $balance = self::walletBalance($user);
        $rate = (int) Setting::get('ichancy.display_rate', 100);

        return implode("\n", [
            '💰 <b>شحن رصيد محدد</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📝 أرسل المبلغ بالـ <b>NSP</b> (ليرة سورية جديدة):',
            '',
            '💰 <b>محفظة البوت:</b>',
            '└── <code>' . number_format($balance, 2) . ' NSP (ليرة سورية جديدة)</code>',
            '',
            '💱 <b>سعر العرض:</b>',
            '└── <code>1 NSP (ليرة سورية جديدة) = ' . number_format($rate) . ' NPS (ليرة سورية قديمة)</code>',
            '',
            '📊 <b>الحدود:</b>',
            '├── الحد الأدنى: <b>' . number_format($cfg['min_deposit']) . '</b>',
            '└── الحد الأقصى: <b>' . number_format($cfg['max_deposit']) . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            'أو /cancel للإلغاء.',
        ]);
    }

    public static function confirm(User $user, float $amount): string
    {
        $cfg = self::cfg();
        $account = $user->ichancyAccount;
        $rate = (int) Setting::get('ichancy.display_rate', 100);
        $amountNps = $amount * $rate;

        return implode("\n", [
            '✅ <b>تأكيد الشحن</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💰 <b>من محفظة البوت (NSP - ليرة سورية جديدة):</b>',
            '└── <code>' . number_format($amount, 2) . ' NSP (ليرة سورية جديدة)</code>',
            '',
            '💵 <b>سيصل في IChancy (NPS - ليرة سورية قديمة):</b>',
            '└── <code>' . number_format($amountNps, 2) . ' NPS (ليرة سورية قديمة)</code>',
            '',
            '🎮 <b>حساب IChancy:</b>',
            '└── <code>' . ($account->ichancy_username ?? '—') . '</code>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '⚠️ هل أنت متأكد؟',
        ]);
    }

    private static function cfg(): array
    {
        return [
            'currency'    => 'NSP',
            'min_deposit' => (int) Setting::get('ichancy.min_deposit', 100),
            'max_deposit' => (int) Setting::get('ichancy.max_deposit', 1000000),
        ];
    }

    private static function walletBalance(User $user): float
    {
        $wallet = $user->wallet;
        return $wallet ? (float) $wallet->balance_nsp : 0.0;
    }
}
