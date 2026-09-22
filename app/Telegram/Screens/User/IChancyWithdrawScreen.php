<?php

namespace App\Telegram\Screens\User;

use App\Models\Setting;
use App\Models\User;

class IChancyWithdrawScreen
{
    public static function options(User $user, float $ichancyBalance): string
    {
        $cfg = self::cfg();
        $rate = (int) Setting::get('ichancy.display_rate', 100);
        $balanceNps = $ichancyBalance * $rate;

        return implode("\n", [
            '💸 <b>سحب من IChancy</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎮 <b>حساب IChancy (NPS - ليرة سورية قديمة):</b>',
            '└── <code>' . number_format($balanceNps, 2) . ' NPS (ليرة سورية قديمة)</code>',
            '   <i>(يعادل ' . number_format($ichancyBalance, 2) . ' NSP - ليرة سورية جديدة)</i>',
            '',
            '💡 <i>سيتم التحويل إلى محفظة البوت بالـ NSP (ليرة سورية جديدة)</i>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الحدود (NSP - ليرة سورية جديدة):</b>',
            '├── الحد الأدنى: <b>' . number_format($cfg['min_withdraw']) . '</b>',
            '└── الحد الأقصى: <b>' . number_format($cfg['max_withdraw']) . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            'اختر طريقة السحب:',
        ]);
    }

    public static function askAmount(User $user, float $ichancyBalance): string
    {
        $cfg = self::cfg();
        $rate = (int) Setting::get('ichancy.display_rate', 100);
        $balanceNps = $ichancyBalance * $rate;

        return implode("\n", [
            '💸 <b>سحب رصيد محدد</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📝 أرسل المبلغ بالـ <b>NSP</b> (ليرة سورية جديدة):',
            '',
            '🎮 <b>حساب IChancy:</b>',
            '└── <code>' . number_format($balanceNps, 2) . ' NPS (ليرة سورية قديمة)</code>',
            '   <i>(يعادل ' . number_format($ichancyBalance, 2) . ' NSP - ليرة سورية جديدة)</i>',
            '',
            '📊 <b>الحدود:</b>',
            '├── الحد الأدنى: <b>' . number_format($cfg['min_withdraw']) . '</b>',
            '└── الحد الأقصى: <b>' . number_format($cfg['max_withdraw']) . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            'أو /cancel للإلغاء.',
        ]);
    }

    public static function confirm(User $user, float $amount): string
    {
        $rate = (int) Setting::get('ichancy.display_rate', 100);
        $amountNps = $amount * $rate;

        return implode("\n", [
            '✅ <b>تأكيد السحب</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💵 <b>من حساب IChancy (NPS - ليرة سورية قديمة):</b>',
            '└── <code>' . number_format($amountNps, 2) . ' NPS (ليرة سورية قديمة)</code>',
            '',
            '💰 <b>سيصل في محفظة البوت (NSP - ليرة سورية جديدة):</b>',
            '└── <code>' . number_format($amount, 2) . ' NSP (ليرة سورية جديدة)</code>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '⚠️ هل أنت متأكد؟',
        ]);
    }

    private static function cfg(): array
    {
        return [
            'currency'     => 'NSP',
            'min_withdraw' => (int) Setting::get('ichancy.min_withdraw', 100),
            'max_withdraw' => (int) Setting::get('ichancy.max_withdraw', 1000000),
        ];
    }
}
