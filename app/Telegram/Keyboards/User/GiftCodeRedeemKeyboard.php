<?php

namespace App\Telegram\Keyboards\User;

use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class GiftCodeRedeemKeyboard
{
    // ============================================================
    //  📝 طلب الكود
    // ============================================================
    public static function prompt(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔙 رجوع',
                    callback_data: 'user.dashboard',
                ),
            );
    }

    // ============================================================
    //  ✅ نجاح
    // ============================================================
    public static function success(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            // ─── 🏠 القائمة الرئيسية ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💰 حسابي',
                    callback_data: 'user.profile',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '🏠 القائمة الرئيسية',
                    callback_data: 'user.dashboard',
                    style: ButtonStyle::PRIMARY,
                ),
            );
    }
}
