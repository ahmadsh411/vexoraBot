<?php

namespace App\Telegram\Screens\Admin\Referrals;

use App\Models\ReferralCycle;
use App\Models\ReferralCycleReward;

class ReferralCyclesScreen
{
    /**
     * الصفحة الرئيسية للدورات.
     */
    public static function text(): string
    {
        $currentCycle = ReferralCycle::open()->latestFirst()->first();
        $closedCount = ReferralCycle::closed()->count();

        $totalRewards = (float) ReferralCycle::closed()->sum('total_rewards');
        $totalBurned = (float) ReferralCycle::closed()->sum('total_burned');

        $lines = [
            '📅 <b>إدارة الدورات</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>إحصائيات عامة:</b>',
            '├── 📜 دورات مكتملة: <b>' . $closedCount . '</b>',
            '├── 💰 إجمالي المكافآت: <b>' . number_format($totalRewards, 2) . '</b> NSP (ليرة سورية جديدة)',
            '└── 🔥 إجمالي الحرق: <b>' . number_format($totalBurned, 2) . '</b> NSP (ليرة سورية جديدة)',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
        ];

        if ($currentCycle) {
            $lines[] = '🟢 <b>الدورة الحالية:</b>';
            $lines[] = '├── 📅 ' . $currentCycle->duration_label;
            $lines[] = '├── ⏱️ أيام متبقية: <b>' . $currentCycle->daysRemaining() . '</b>';
            $lines[] = '├── 💰 مكافآت: <b>' . number_format((float) $currentCycle->total_rewards, 2) . '</b> NSP (ليرة سورية جديدة)';
            $lines[] = '└── 🔥 حرق: <b>' . number_format((float) $currentCycle->total_burned, 2) . '</b> NSP (ليرة سورية جديدة)';
        } else {
            $lines[] = '❌ <b>لا توجد دورة مفتوحة حالياً</b>';
        }

        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━';

        return implode("\n", $lines);
    }

    /**
     * تفاصيل دورة.
     */
    public static function detailsText(ReferralCycle $cycle): string
    {
        $rewards = ReferralCycleReward::where('cycle_id', $cycle->id)
            ->with('referrer:id,username')
            ->get();

        $lines = [
            '📅 <b>تفاصيل الدورة</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🆔 <b>المعرف:</b> <code>' . $cycle->id . '</code>',
            '📊 <b>الحالة:</b> ' . $cycle->status_label,
            '📅 <b>الفترة:</b> ' . $cycle->duration_label,
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الإحصائيات:</b>',
            '├── 🔥 إجمالي الحرق: <b>' . number_format((float) $cycle->total_burned, 2) . '</b> NSP (ليرة سورية جديدة)',
            '├── 💰 إجمالي المكافآت: <b>' . number_format((float) $cycle->total_rewards, 2) . '</b> NSP (ليرة سورية جديدة)',
            '├── 👥 مُحيلون: <b>' . $cycle->referrers_count . '</b>',
            '└── 👤 مُحالون: <b>' . $cycle->referred_count . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '⏱️ <b>التوقيت:</b>',
            '├── بدأت: ' . $cycle->created_at?->format('Y-m-d H:i'),
        ];

        if ($cycle->processed_at) {
            $lines[] = '└── عُولجت: ' . $cycle->processed_at->format('Y-m-d H:i');
        } elseif ($cycle->isOpen()) {
            $lines[] = '└── 🟢 جارية';
        }

        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━';

        if ($rewards->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '🏆 <b>أعلى 10 مُحيلين:</b>';

            foreach ($rewards->sortByDesc('total_reward')->take(10) as $index => $reward) {
                $medal = match ($index) {
                    0 => '🥇',
                    1 => '🥈',
                    2 => '🥉',
                    default => '  ',
                };

                $username = $reward->referrer?->username ?? 'محذوف';

                $lines[] = "{$medal} <b>" . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</b>';
                $lines[] = '   💰 ' . number_format((float) $reward->total_reward, 2) . ' NSP (ليرة سورية جديدة)';
                $lines[] = '   🔥 ' . number_format((float) $reward->total_burned_l1 + (float) $reward->total_burned_l2, 2);
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * قائمة الدورات السابقة.
     */
    public static function listText(): string
    {
        $cycles = ReferralCycle::closed()
            ->latestFirst()
            ->limit(10)
            ->get();

        if ($cycles->isEmpty()) {
            return implode("\n", [
                '📜 <b>الدورات السابقة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '❌ لا توجد دورات مكتملة.',
            ]);
        }

        $lines = [
            '📜 <b>الدورات السابقة</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 العدد: <b>' . $cycles->count() . '</b>',
            '',
        ];

        foreach ($cycles as $cycle) {
            $lines[] = '📅 <b>' . $cycle->duration_label . '</b>';
            $lines[] = '   💰 ' . number_format((float) $cycle->total_rewards, 2) . ' NSP (ليرة سورية جديدة)';
            $lines[] = '   👥 ' . $cycle->referrers_count . ' مُحيل';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * تأكيد إغلاق الدورة.
     */
    public static function confirmCloseText(ReferralCycle $cycle): string
    {
        return implode("\n", [
            '⚠️ <b>تأكيد إغلاق الدورة</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📅 <b>الدورة:</b> ' . $cycle->duration_label,
            '📊 <b>الحالة:</b> ' . $cycle->status_label,
            '',
            '⚠️ <b>سيتم:</b>',
            '├── جمع إحصائيات الحرق',
            '├── حساب المكافآت',
            '├── إضافة المكافآت للمُحيلين',
            '└── إنشاء دورة جديدة',
            '',
            '🚨 <b>لا يمكن التراجع!</b>',
        ]);
    }

    /**
     * مكافآت دورة.
     */
    public static function rewardsText(ReferralCycle $cycle): string
    {
        $rewards = ReferralCycleReward::where('cycle_id', $cycle->id)
            ->with('referrer:id,username')
            ->orderByDesc('total_reward')
            ->get();

        if ($rewards->isEmpty()) {
            return implode("\n", [
                '🏆 <b>مكافآت الدورة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '❌ لا توجد مكافآت.',
            ]);
        }

        $totalRewards = (float) $rewards->sum('total_reward');

        $lines = [
            '🏆 <b>مكافآت الدورة</b>',
            '📅 ' . $cycle->duration_label,
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>العدد:</b> ' . $rewards->count(),
            '💰 <b>الإجمالي:</b> ' . number_format($totalRewards, 2) . ' NSP (ليرة سورية جديدة)',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
        ];

        foreach ($rewards->take(20) as $index => $reward) {
            $number = $index + 1;
            $username = $reward->referrer?->username ?? 'محذوف';

            $lines[] = "{$number}. <b>" . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</b>';
            $lines[] = '   💰 <b>' . number_format((float) $reward->total_reward, 2) . '</b> NSP (ليرة سورية جديدة)';
            $lines[] = '   🔥 حرق L1: ' . number_format((float) $reward->total_burned_l1, 2);
            $lines[] = '   🔥 حرق L2: ' . number_format((float) $reward->total_burned_l2, 2);
            $lines[] = '   🎁 L1: ' . number_format((float) $reward->reward_l1, 2);
            $lines[] = '   🎁 L2: ' . number_format((float) $reward->reward_l2, 2);
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
