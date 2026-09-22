<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Users;

use App\Models\User;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class NewUsersKeyboard
{
    public static function make(): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        $users = User::whereNull('admin_seen_at')
            ->latest('created_at')
            ->limit(10)
            ->get();

        foreach ($users as $user) {
            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            if ($name === '') {
                $name = $user->username;
            }

            if (mb_strlen($name) > 30) {
                $name = mb_substr($name, 0, 30) . '…';
            }

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🆕 ' . $name,
                    callback_data: "admin.users.show.{$user->id}",
                    style: ButtonStyle::PRIMARY,
                ),
            );
        }

        if ($users->isNotEmpty()) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '✅ تم الاطلاع على الكل',
                    callback_data: 'admin.users.new.mark-all-seen',
                    style: ButtonStyle::SUCCESS,
                ),
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '⬅️ رجوع',
                callback_data: 'admin.users',
            ),
        );

        return $keyboard;
    }
}
