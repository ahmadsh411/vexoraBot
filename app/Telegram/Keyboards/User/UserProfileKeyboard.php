<?php

namespace App\Telegram\Keyboards\User;

use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class UserProfileKeyboard
{
    public static function make(): InlineKeyboardMarkup
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
}
