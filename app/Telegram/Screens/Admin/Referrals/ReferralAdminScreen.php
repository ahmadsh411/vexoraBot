<?php

namespace App\Telegram\Screens\Admin\Referrals;

use App\Models\Referral;
use App\Models\ReferralCycle;
use App\Models\ReferralReward;
use App\Models\ReferralSetting;

class ReferralAdminScreen
{
    public static function text(): string
    {
        $settings = ReferralSetting::current();

        // ============================================================
        //  الإحصائيات العامة
        // ============================================================
        $totalReferrals = Referral::count();
        $l1Count = Referral::level1()->count();
        $l2Count = Referral::level2()->count();

        $totalRewards = (float) ReferralReward::paid()->sum('amount');

        // إحصائيات هذا الأسبوع
        $weekRewards = (float) ReferralReward::paid()
            ->where('paid_at', '>=', now()->subDays(7))
            ->sum('amount');

        $weekReferrals = Referral::where('created_at', '>=', now()->subDays(7))->count();

        // ============================================================
        //  الدورة الحالية
        // ============================================================
        $cycle = ReferralCycle::open()->latestFirst()->first();

        $cycleLines = ['❌ لا توجد دورة مفتوحة'];

        if ($cycle) {
            $cycleLines = [
                '├── 📅 ' . $cycle->duration_label,
                '├── 🟢 مفتوحة',
                '├── ⏱️ أيام متبقية: <b>' . $cycle->daysRemaining() . '</b>',
                '└── 💰 إجمالي: <b>' . number_format((float) $cycle->total_rewards, 2) . '</b> NSP (ليرة سورية جديدة)',
            ];
        }

        // ============================================================
        //  البناء
        // ============================================================
        return implode("\n", [
            '🎯 <b>إدارة الإحالات</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الإحصائيات العامة:</b>',
            '├── 👥 إجمالي المُحالين: <b>' . $totalReferrals . '</b>',
            '├── 🥇 Level 1: <b>' . $l1Count . '</b>',
            '├── 🥈 Level 2: <b>' . $l2Count . '</b>',
            '└── 💰 إجمالي المكافآت: <b>' . number_format($totalRewards, 2) . '</b> NSP (ليرة سورية جديدة)',
            '',
            '📈 <b>هذا الأسبوع:</b>',
            '├── 👥 مُحالون جدد: <b>' . $weekReferrals . '</b>',
            '└── 💰 مكافآت: <b>' . number_format($weekRewards, 2) . '</b> NSP (ليرة سورية جديدة)',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📅 <b>الدورة الحالية:</b>',
            ...$cycleLines,
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>النسب الحالية:</b>',
            '├── ⚡ فوري:',
            '│   ├── L1: <b>' . $settings->instant_level_1_percent . '%</b>',
            '│   └── L2: <b>' . $settings->instant_level_2_percent . '%</b>',
            '└── 📅 دوري:',
            '    ├── L1: <b>' . $settings->cycle_level_1_percent . '%</b>',
            '    └── L2: <b>' . $settings->cycle_level_2_percent . '%</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
        ]);
    }
}
