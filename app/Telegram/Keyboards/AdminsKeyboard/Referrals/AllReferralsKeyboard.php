<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Referrals;

use App\Models\Referral;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class AllReferralsKeyboard
{
    private const PER_PAGE = 10;

    public static function make(int $page = 1): InlineKeyboardMarkup
    {
        $total = Referral::distinct('referrer_id')->count('referrer_id');
        $totalPages = (int) ceil($total / self::PER_PAGE);

        $keyboard = InlineKeyboardMarkup::make();

        if ($totalPages > 1) {
            $navRow = [];

            if ($page > 1) {
                $navRow[] = InlineKeyboardButton::make(
                    text: '◀️ السابق',
                    callback_data: 'admin.referrals.all.page.' . ($page - 1),
                    style: ButtonStyle::PRIMARY,
                );
            }

            $navRow[] = InlineKeyboardButton::make(
                text: "📄 {$page}/{$totalPages}",
                callback_data: 'admin.referrals.all.noop',
            );

            if ($page < $totalPages) {
                $navRow[] = InlineKeyboardButton::make(
                    text: 'التالي ▶️',
                    callback_data: 'admin.referrals.all.page.' . ($page + 1),
                    style: ButtonStyle::PRIMARY,
                );
            }

            $keyboard->addRow(...$navRow);
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
