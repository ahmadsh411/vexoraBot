<?php

namespace App\Telegram\Screens\Admin\Referrals;

use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\User;

class TopReferrersScreen
{
    /**
     * نص أفضل المُحيلين.
     */
    public static function text(string $period = 'all'): string
    {
        // تحديد الفترة
        $since = match ($period) {
            'today' => now()->startOfDay(),
            'week'  => now()->subDays(7),
            'month' => now()->subDays(30),
            default => null,
        };

        // اجلب أفضل 10 مُحيلين
        $query = User::where('referrals_count', '>', 0)
            ->orderByDesc('referral_earnings');

        if ($since) {
            $query->whereHas('referralRewards', function ($q) use ($since) {
                $q->where('paid_at', '>=', $since);
            });
        }

        $topReferrers = $query->limit(10)->get();

        if ($topReferrers->isEmpty()) {
            return implode("\n", [
                '🏆 <b>أفضل المُحيلين</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '❌ لا يوجد مُحيلون.',
            ]);
        }

        $periodLabel = match ($period) {
            'today' => '📅 اليوم',
            'week'  => '📆 آخر 7 أيام',
            'month' => '📅 آخر 30 يوماً',
            default => '📊 كل الفترات',
        };

        $lines = [
            '🏆 <b>أفضل المُحيلين</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الفترة:</b> ' . $periodLabel,
            '',
        ];

        foreach ($topReferrers as $index => $user) {
            $medal = match ($index) {
                0 => '🥇',
                1 => '🥈',
                2 => '🥉',
                default => '  ',
            };

            // احسب مكافآت المستخدم في الفترة
            $earningsQuery = ReferralReward::forReferrer($user->id)->paid();

            if ($since) {
                $earningsQuery->where('paid_at', '>=', $since);
            }

            $earnings = (float) $earningsQuery->sum('amount');

            $directCount = $user->directReferrals()->count();
            $level2Count = $user->level2Referrals()->count();

            $lines[] = "{$medal} <b>" . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</b>';
            $lines[] = '   💰 <b>' . number_format($earnings, 2) . '</b> NSP (ليرة سورية جديدة)';
            $lines[] = '   👥 L1: ' . $directCount . ' | L2: ' . $level2Count;
            $lines[] = '   🆔 <code>' . $user->id . '</code>';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
