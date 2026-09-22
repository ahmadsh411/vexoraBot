<?php

namespace App\Telegram\Keyboards\User;

use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class IChancyWithdrawKeyboard
{
    // ============================================================
    //  📋 خيارات السحب
    // ============================================================
    public static function options(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            // ─── 💸 سحب كامل ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💸 سحب كامل الرصيد',
                    callback_data: 'user.ichancy.withdraw.full',
                    style: ButtonStyle::SUCCESS,
                ),
            )
            // ─── ✏️ سحب مخصص ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✏️ سحب رصيد محدد',
                    callback_data: 'user.ichancy.withdraw.custom',
                    style: ButtonStyle::PRIMARY,
                ),
            )
            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'user.dashboard',
                ),
            );
    }

    // ============================================================
    //  ✅ تأكيد
    // ============================================================
    public static function confirm(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✅ تأكيد',
                    callback_data: 'user.ichancy.withdraw.confirm',
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '✖️ إلغاء',
                    callback_data: 'user.dashboard',
                ),
            );
    }
}
