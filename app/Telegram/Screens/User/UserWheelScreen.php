<?php

namespace App\Telegram\Screens\User;

use App\Models\WheelPrize;
use App\Models\WheelSpin;

class UserWheelScreen
{
    // ============================================================
    //  🏠 الرئيسية
    // ============================================================

    public static function main(array $state): string
    {
        $available = $state['available_spins'];
        $today     = $state['spins_today'];
        $limit     = $state['daily_limit'];
        $remaining = $state['remaining_today'];

        return implode("\n", [
            '🎡 <b>عجلة الحظ</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎟 <b>اللفات المتاحة:</b> <b>' . $available . '</b>',
            '',
            '📅 <b>اليوم:</b> <b>' . $today . '</b> / <b>' . $limit . '</b>',
            '⏳ <b>متبقٍ اليوم:</b> <b>' . $remaining . '</b>',
            '',
            '💰 <b>إجمالي المكاسب:</b> <b>'
                . number_format($state['total_won_amount'], 2)
                . '</b> NSP (ليرة سورية جديدة)',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💡 اضغط 🎰 لبدء اللفة!',
        ]);
    }

    // ============================================================
    //  🎰 نتيجة اللفة
    // ============================================================

    public static function spinResult(WheelPrize $prize, WheelSpin $spin): string
    {
        $text = "🎊 <b>مبروك!</b>\n\n";

        if ($prize->isRecycle()) {
            $text .= "♻️ <b>{$prize->name}</b>\n\n";
            $text .= "🎉 حصلت على <b>لفة مجانية</b>!\n";
            $text .= "💫 لم تُخصم من رصيد لفاتك.";
        } elseif ($prize->isEmpty()) {
            $text .= "{$prize->icon} <b>{$prize->name}</b>\n\n";
            $text .= "💔 لا شيء هذه المرة.\n";
            $text .= "🍀 حظ أوفر في المرة القادمة!";
        } else {
            $text .= "{$prize->icon} <b>{$prize->name}</b>\n\n";

            // ✅ تسمية العملة حسب النوع
            $currencyLabel = match ($spin->currency) {
                'NSP'   => 'NSP (ليرة سورية جديدة)',
                'USD'   => 'USD (دولار)',
                'NPS'   => 'NPS (ليرة سورية قديمة)',
                default => $spin->currency,
            };

            $text .= "💰 القيمة: <b>" . number_format((float) $spin->won_value, 2) . " {$currencyLabel}</b>\n";
            $text .= "✅ تمت إضافتها لرصيدك.";
        }

        return $text;
    }

    // ============================================================
    //  📜 السجل
    // ============================================================

    public static function history($spins): string
    {
        $lines = [
            '📜 <b>آخر 10 لفات</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
        ];

        foreach ($spins as $spin) {
            $icon = $spin->prize?->icon ?? '❓';
            $name = $spin->prize?->name ?? 'محذوفة';
            $date = $spin->created_at->format('Y-m-d H:i');

            // ✅ تسمية العملة
            $currencyLabel = match ($spin->currency) {
                'NSP'   => 'NSP (ليرة سورية جديدة)',
                'USD'   => 'USD (دولار)',
                'NPS'   => 'NPS (ليرة سورية قديمة)',
                default => $spin->currency,
            };

            $value = $spin->won_value > 0
                ? ' — ' . number_format((float) $spin->won_value, 2) . ' ' . $currencyLabel
                : '';

            $lines[] = "{$icon} {$name}{$value}";
            $lines[] = "   <i>{$date}</i>";
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
