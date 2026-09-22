<?php

namespace App\Telegram\Screens\Admin\System;

use App\Models\Setting;

class IChancySettingsScreen
{
    public static function text(): string
    {
        $enabled = (bool) Setting::get('ichancy.enabled', true);
        $minDep  = (int)  Setting::get('ichancy.min_deposit', 100);
        $maxDep  = (int)  Setting::get('ichancy.max_deposit', 1000000);
        $minWit  = (int)  Setting::get('ichancy.min_withdraw', 100);
        $maxWit  = (int)  Setting::get('ichancy.max_withdraw', 1000000);
        $rate    = (int)  Setting::get('ichancy.display_rate', 100);

        $statusIcon = $enabled ? '🟢' : '🔴';
        $statusText = $enabled ? 'مُفعّل' : 'معطّل';

        return implode("\n", [
            '🎮 <b>إعدادات IChancy</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            $statusIcon . ' <b>الحالة:</b> ' . $statusText,
            '💱 <b>العملة:</b> <code>NSP (ليرة سورية جديدة)</code>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💰 <b>حدود الإيداع:</b>',
            '├── الحد الأدنى: <b>' . number_format($minDep) . ' NSP (ليرة سورية جديدة)</b>',
            '└── الحد الأقصى: <b>' . number_format($maxDep) . ' NSP (ليرة سورية جديدة)</b>',
            '',
            '📤 <b>حدود السحب:</b>',
            '├── الحد الأدنى: <b>' . number_format($minWit) . ' NSP (ليرة سورية جديدة)</b>',
            '└── الحد الأقصى: <b>' . number_format($maxWit) . ' NSP (ليرة سورية جديدة)</b>',
            '',
            '💱 <b>سعر العرض (NSP (ليرة سورية جديدة) → NPS (ليرة سورية قديمة)):</b>',
            '└── <code>1 NSP (ليرة سورية جديدة) = ' . number_format($rate) . ' NPS (ليرة سورية قديمة)</code>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💡 <i>اضغط على أي إعداد لتعديله</i>',
        ]);
    }

    public static function askValue(string $key): string
    {
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            return '⚠️ الإعداد غير موجود.';
        }

        $lines = [
            '✏️ <b>تعديل:</b> ' . $setting->label,
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📝 <b>القيمة الحالية:</b>',
            '<code>' . $setting->display_value . '</code>',
            '',
            'أرسل القيمة الجديدة:',
        ];

        if ($setting->type === Setting::TYPE_INT) {
            $lines[] = '⚠️ أرسل رقماً صحيحاً فقط';
        }

        if ($setting->type === Setting::TYPE_BOOL) {
            $lines[] = '⚠️ أرسل: <code>1</code> (تفعيل) أو <code>0</code> (تعطيل)';
        }

        $lines[] = '';
        $lines[] = 'أو /cancel للإلغاء.';

        return implode("\n", $lines);
    }
}
