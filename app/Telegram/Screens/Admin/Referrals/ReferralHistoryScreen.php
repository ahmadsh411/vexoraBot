<?php

namespace App\Telegram\Screens\Admin\Referrals;

use App\Models\ReferralReward;

class ReferralHistoryScreen
{
    public const PER_PAGE = 10;

    public static function text(string $filter = 'all', int $page = 1): string
    {
        $page = max(1, $page);

        $query = ReferralReward::paid()
            ->with(['referrer:id,username', 'referred:id,username'])
            ->latest('paid_at');

        $title = match ($filter) {
            'instant' => '⚡ <b>المكافآت الفورية</b>',
            'cycle'   => '📅 <b>مكافآت الدورات</b>',
            'l1'      => '🥇 <b>مكافآت Level 1</b>',
            'l2'      => '🥈 <b>مكافآت Level 2</b>',
            default   => '📜 <b>سجل المكافآت</b>',
        };

        match ($filter) {
            'instant' => $query->instant(),
            'cycle'   => $query->cycle(),
            'l1'      => $query->level1(),
            'l2'      => $query->level2(),
            default   => null,
        };

        $total = (clone $query)->count();
        $totalAmount = (float) (clone $query)->sum('amount');
        $rewards = $query->forPage($page, self::PER_PAGE)->get();

        if ($rewards->isEmpty()) {
            return implode("\n", [
                $title,
                '━━━━━━━━━━━━━━━━━━',
                '',
                '❌ لا يوجد سجل.',
            ]);
        }

        $totalPages = (int) ceil($total / self::PER_PAGE);

        $lines = [
            $title,
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>العدد:</b> ' . $total,
            '💰 <b>الإجمالي:</b> ' . number_format($totalAmount, 2) . ' NSP (ليرة سورية جديدة)',
            '📄 <b>الصفحة:</b> ' . $page . ' / ' . $totalPages,
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
        ];

        foreach ($rewards as $index => $reward) {
            $number = (($page - 1) * self::PER_PAGE) + $index + 1;

            $icon = match ($reward->type) {
                'instant' => '⚡',
                'cycle'   => '📅',
                default   => '🎁',
            };

            $levelIcon = $reward->level === 1 ? '🥇' : '🥈';
            $referrerName = $reward->referrer?->username ?? 'محذوف';
            $referredName = $reward->referred?->username;

            $lines[] = "{$number}. {$icon} {$levelIcon} <b>" . number_format((float) $reward->amount, 2) . ' ' . $reward->currency . '</b>';
            $lines[] = '   👤 المُحيل: <code>' . htmlspecialchars($referrerName, ENT_QUOTES, 'UTF-8') . '</code>';

            if ($referredName) {
                $lines[] = '   🎯 المُحال: <code>' . htmlspecialchars($referredName, ENT_QUOTES, 'UTF-8') . '</code>';
            } else {
                $lines[] = '   🎯 المُحال: <i>دورة كاملة</i>';
            }

            $lines[] = '   📊 النسبة: ' . $reward->commission_percent . '%';
            $lines[] = '   📅 ' . $reward->paid_at?->format('Y-m-d H:i');
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
