<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Users;

use App\Models\User;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class TrashedUsersKeyboard
{
    public static function make(): InlineKeyboardMarkup
    {
        $users = User::onlyTrashed()
            ->latest('deleted_at')
            ->limit(20)
            ->get();

        $keyboard = InlineKeyboardMarkup::make();

        if ($users->isEmpty()) {
            return $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: 'admin.users',
                ),
            );
        }

        foreach ($users as $user) {
            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            if ($name === '') {
                $name = $user->username;
            }

            if (mb_strlen($name) > 25) {
                $name = mb_substr($name, 0, 25) . '…';
            }

            $daysAgo = $user->deleted_at?->diffInDays(now()) ?? 0;
            $timeLabel = $daysAgo === 0 ? 'اليوم' : "قبل {$daysAgo} يوم";

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🗑️ ' . $name . ' — ' . $timeLabel,
                    callback_data: "admin.users.trashed.show.{$user->id}",
                    style: ButtonStyle::DANGER,
                ),
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '♻️ استعادة الكل (' . $users->count() . ')',
                callback_data: 'admin.users.trashed.restore-all',
                style: ButtonStyle::SUCCESS,
            ),
        );

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '⬅️ رجوع',
                callback_data: 'admin.users',
            ),
        );

        return $keyboard;
    }

    public static function detailsKeyboard(User $user): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            // 🟢 استعادة
            ->addRow(
                InlineKeyboardButton::make(
                    text: '♻️ استعادة',
                    callback_data: "admin.users.trashed.restore.{$user->id}",
                    style: ButtonStyle::SUCCESS,
                ),
            )
            // 🔴 حذف نهائي
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💥 حذف نهائي',
                    callback_data: "admin.users.trashed.force.{$user->id}",
                    style: ButtonStyle::DANGER,
                ),
            )
            // ⚪ رجوع
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: 'admin.users.trashed',
                ),
            );
    }

    public static function confirmForceKeyboard(User $user): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            // 🔴 تأكيد
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💥 نعم، احذف نهائياً',
                    callback_data: "admin.users.trashed.force-confirm.{$user->id}",
                    style: ButtonStyle::DANGER,
                ),
            )
            // ⚪ إلغاء
            ->addRow(
                InlineKeyboardButton::make(
                    text: '❌ إلغاء',
                    callback_data: "admin.users.trashed.show.{$user->id}",
                ),
            );
    }
}
