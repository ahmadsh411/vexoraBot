<?php

// ✅ تصحيح: App (بحرف كبير)
namespace App\Telegram\Screens\Admin\Referrals;

use App\Models\ReferralSetting;

class ReferralSettingsScreen
{
    public static function text(): string
    {
        $settings = ReferralSetting::current();

        return implode("\n", [
            '⚙️ <b>إعدادات النسب</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>النسب الحالية:</b>',
            '',
            '⚡ <b>الفوري</b> (على الإيداع):',
            '├── 🥇 Level 1: <b>' . $settings->instant_level_1_percent . '%</b>',
            '└── 🥈 Level 2: <b>' . $settings->instant_level_2_percent . '%</b>',
            '',
            '📅 <b>الدوري</b> (على الحرق):',
            '├── 🥇 Level 1: <b>' . $settings->cycle_level_1_percent . '%</b>',
            '└── 🥈 Level 2: <b>' . $settings->cycle_level_2_percent . '%</b>',
            '',
            '📆 <b>مدة الدورة:</b> <b>' . $settings->cycle_days . ' يوم</b>',
            '',
            '🟢 <b>الحالة:</b> ' . ($settings->is_active ? 'مفعّل' : 'معطّل'),
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💡 اضغط ➕ / ➖ للتعديل',
            '',
            '⚠️ <b>التغييرات فورية</b> — تُطبَّق على العمليات الجديدة.',
        ]);
    }
}
