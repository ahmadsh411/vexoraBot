<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Referrals;

use App\Models\ReferralReward;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ReferralHistoryKeyboard
{
    public const PER_PAGE = 10;

    public static function make(string $filter = 'all', int $page = 1): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        // ─── الفلاتر ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: $filter === 'all' ? '✅ الكل' : '📜 الكل',
                callback_data: 'admin.referrals.history.all',
                style: $filter === 'all' ? ButtonStyle::SUCCESS : ButtonStyle::PRIMARY,
            ),
            InlineKeyboardButton::make(
                text: $filter === 'instant' ? '✅ فوري' : '⚡ فوري',
                callback_data: 'admin.referrals.history.instant',
                style: $filter === 'instant' ? ButtonStyle::SUCCESS : ButtonStyle::PRIMARY,
            ),
        );

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: $filter === 'cycle' ? '✅ دوري' : '📅 دوري',
                callback_data: 'admin.referrals.history.cycle',
                style: $filter === 'cycle' ? ButtonStyle::SUCCESS : ButtonStyle::PRIMARY,
            ),
        );

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: $filter === 'l1' ? '✅ L1' : '🥇 L1',
                callback_data: 'admin.referrals.history.l1',
                style: $filter === 'l1' ? ButtonStyle::SUCCESS : ButtonStyle::PRIMARY,
            ),
            InlineKeyboardButton::make(
                text: $filter === 'l2' ? '✅ L2' : '🥈 L2',
                callback_data: 'admin.referrals.history.l2',
                style: $filter === 'l2' ? ButtonStyle::SUCCESS : ButtonStyle::PRIMARY,
            ),
        );

        // ─── التنقل ───
        $query = ReferralReward::paid();

        match ($filter) {
            'instant' => $query->instant(),
            'cycle'   => $query->cycle(),
            'l1'      => $query->level1(),
            'l2'      => $query->level2(),
            default   => null,
        };

        $total = $query->count();
        $totalPages = (int) ceil($total / self::PER_PAGE);

        if ($totalPages > 1) {
            $navRow = [];

            if ($page > 1) {
                $navRow[] = InlineKeyboardButton::make(
                    text: '◀️ السابق',
                    callback_data: "admin.referrals.history.{$filter}.page." . ($page - 1),
                    style: ButtonStyle::PRIMARY,
                );
            }

            $navRow[] = InlineKeyboardButton::make(
                text: "📄 {$page}/{$totalPages}",
                callback_data: 'admin.referrals.history.noop',
            );

            if ($page < $totalPages) {
                $navRow[] = InlineKeyboardButton::make(
                    text: 'التالي ▶️',
                    callback_data: "admin.referrals.history.{$filter}.page." . ($page + 1),
                    style: ButtonStyle::PRIMARY,
                );
            }

            if (! empty($navRow)) {
                $keyboard->addRow(...$navRow);
            }
        }

        // ─── رجوع (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '⬅️ رجوع',
                callback_data: 'admin.referrals',
            ),
        );

        return $keyboard;
    }
}
