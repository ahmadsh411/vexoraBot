<?php

namespace App\Telegram\Keyboards\User;

use App\Models\Wheel;
use App\Models\WheelUserState;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class UserWheelKeyboard
{
    public static function main(array $state, Wheel $wheel): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        $available = $state['available_spins'];
        $today     = $state['spins_today'];
        $limit     = $wheel->daily_limit;

        $canSpin = $available > 0 && $today < $limit;

        // ─── 🎰 لف العجلة ───
        if ($canSpin) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🎰 لف العجلة (' . $available . ')',
                    callback_data: 'user.wheel.spin',
                    style: ButtonStyle::SUCCESS,
                ),
            );
        } elseif ($available <= 0) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🚫 لا توجد لفات',
                    callback_data: 'user.wheel.refresh',
                    style: ButtonStyle::DANGER,
                ),
            );
        } else {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '⏰ وصلت الحد اليومي',
                    callback_data: 'user.wheel.refresh',
                    style: ButtonStyle::DANGER,
                ),
            );
        }

        // ─── 📜 السجل ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '📜 سجل اللفات',
                callback_data: 'user.wheel.history',
                style: ButtonStyle::PRIMARY,
            ),
        );

        // ─── 🔄 تحديث ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '🔄 تحديث',
                callback_data: 'user.wheel.refresh',
                style: ButtonStyle::PRIMARY,
            ),
        );

        // ─── ↩️ رجوع (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '↩️ رجوع للوحة',
                callback_data: 'user.dashboard',
            ),
        );

        return $keyboard;
    }
}
