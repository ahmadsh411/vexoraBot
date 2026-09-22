<?php

namespace App\Telegram\Keyboards\User;

use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class GuideKeyboard
{
    public static function index(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع للقائمة',
                    callback_data: 'user.dashboard',
                ),
            );
    }
}
