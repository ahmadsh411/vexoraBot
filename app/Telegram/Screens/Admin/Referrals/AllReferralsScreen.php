<?php

namespace App\Telegram\Screens\Admin\Referrals;

use App\Models\Referral;
use Illuminate\Support\Facades\Log;

class AllReferralsScreen
{
    /**
     * نص كل المُحيلين.
     */
    public static function text(int $page = 1): string
    {
        $perPage = 10;
        $page = max(1, $page);

        // كل المُحيلين (الذين لهم إحالات)
        $total = Referral::distinct('referrer_id')->count('referrer_id');

        $referrerIds = Referral::select('referrer_id')
            ->groupBy('referrer_id')
            ->orderByRaw('COUNT(*) DESC')
            ->forPage($page, $perPage)
            ->pluck('referrer_id');

        $referrers = \App\Models\User::whereIn('id', $referrerIds)
            ->orderByDesc('referral_earnings')
            ->get();

        if ($referrers->isEmpty()) {
            return implode("\n", [
                '👥 <b>كل المُحيلين</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '❌ لا يوجد مُحيلون.',
            ]);
        }

        $totalPages = (int) ceil($total / $perPage);

        $lines = [
            '👥 <b>كل المُحيلين</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الإجمالي:</b> ' . $total,
            '📄 <b>الصفحة:</b> ' . $page . ' / ' . $totalPages,
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
        ];

        foreach ($referrers as $index => $user) {
            $number = (($page - 1) * $perPage) + $index + 1;

            $l1Count = Referral::forReferrer($user->id)->level1()->count();
            $l2Count = Referral::forReferrer($user->id)->level2()->count();

            $type = match ($user->referral_type) {
                'instant' => '⚡',
                'cycle'   => '📅',
                default   => '❔',
            };

            $lines[] = "{$number}. {$type} <b>" . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</b>';
            $lines[] = '   🆔 <code>' . $user->id . '</code>';
            $lines[] = '   👥 L1: ' . $l1Count . ' | L2: ' . $l2Count;
            $lines[] = '   💰 ' . number_format((float) $user->referral_earnings, 2) . ' NSP (ليرة سورية جديدة)';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * كيبورد التنقل.
     */
    public static function keyboard(int $page = 1): \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup
    {
        $perPage = 10;
        $total = Referral::distinct('referrer_id')->count('referrer_id');
        $totalPages = (int) ceil($total / $perPage);

        $keyboard = \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup::make();

        // التنقل
        if ($totalPages > 1) {
            $navRow = [];

            if ($page > 1) {
                $navRow[] = \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                    text: '◀️ السابق',
                    callback_data: 'admin.referrals.all.page.' . ($page - 1),
                );
            }

            $navRow[] = \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                text: "📄 {$page}/{$totalPages}",
                callback_data: 'admin.referrals.all.noop',
            );

            if ($page < $totalPages) {
                $navRow[] = \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                    text: 'التالي ▶️',
                    callback_data: 'admin.referrals.all.page.' . ($page + 1),
                );
            }

            if (! empty($navRow)) {
                $keyboard->addRow(...$navRow);
            }
        }

        // الرجوع
        $keyboard->addRow(
            \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                text: '⬅️ رجوع',
                callback_data: 'admin.referrals',
            ),
        );

        return $keyboard;
    }
}
