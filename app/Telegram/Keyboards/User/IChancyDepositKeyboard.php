<?php

namespace App\Telegram\Keyboards\User;

use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class IChancyDepositKeyboard
{
    // ============================================================
    //  📋 خيارات الشحن
    // ============================================================
    public static function options(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            // ─── 💰 شحن كامل ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💰 شحن كامل الرصيد',
                    callback_data: 'user.ichancy.deposit.full',
                    style: ButtonStyle::SUCCESS,
                ),
            )
            // ─── ✏️ شحن مخصص ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✏️ شحن رصيد محدد',
                    callback_data: 'user.ichancy.deposit.custom',
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
                    callback_data: 'user.ichancy.deposit.confirm',
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '✖️ إلغاء',
                    callback_data: 'user.dashboard',
                ),
            );
    }
}
