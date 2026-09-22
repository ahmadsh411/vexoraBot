<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Users;

use Illuminate\Support\Collection;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ListUserKeyboard
{
    private const PER_ROW = 2;
    private const MAX_NAME = 18;

    public static function make(
        Collection $users,
        int $currentPage = 1,
        bool $hasPrev = false,
        bool $hasNext = false,
    ): InlineKeyboardMarkup {
        $keyboard = InlineKeyboardMarkup::make();

        // قائمة فارغة
        if ($users->isEmpty()) {
            return $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'admin.users',
                ),
            );
        }

        // تجميع أزرار المستخدمين
        $userButtons = [];

        foreach ($users as $user) {
            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            if ($name === '') {
                $name = $user->username;
            }

            if (mb_strlen($name) > self::MAX_NAME) {
                $name = mb_substr($name, 0, self::MAX_NAME) . '…';
            }

            // 🟢 نشط / 🔴 موقوف
            $status = $user->is_active ? '🟢' : '🔴';
            $style  = $user->is_active ? ButtonStyle::SUCCESS : ButtonStyle::DANGER;

            // 👑 مشرف أساسي / 🛡️ أدمن عادي
            $badge = '';
            if ($user->is_admin) {
                $badge = ($user->is_super_admin ?? false) ? '👑' : '🛡️';
            }

            $userButtons[] = InlineKeyboardButton::make(
                text: "{$status} {$name}{$badge}",
                callback_data: "admin.users.show.{$user->id}",
                style: $style,
            );
        }

        // إضافة الأزرار في صفوف
        foreach (array_chunk($userButtons, self::PER_ROW) as $row) {
            $keyboard->addRow(...$row);
        }

        // صف التنقل
        $navRow = [];

        if ($hasPrev) {
            $navRow[] = InlineKeyboardButton::make(
                text: '◀️ السابق',
                callback_data: 'admin.users.list.page.' . ($currentPage - 1),
                style: ButtonStyle::PRIMARY,
            );
        }

        $navRow[] = InlineKeyboardButton::make(
            text: "📄 {$currentPage}",
            callback_data: 'admin.users.list.noop',
        );

        if ($hasNext) {
            $navRow[] = InlineKeyboardButton::make(
                text: 'التالي ▶️',
                callback_data: 'admin.users.list.page.' . ($currentPage + 1),
                style: ButtonStyle::PRIMARY,
            );
        }

        if (! empty($navRow)) {
            $keyboard->addRow(...$navRow);
        }

        // زر الرجوع
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '↩️ رجوع',
                callback_data: 'admin.users',
            ),
        );

        return $keyboard;
    }
}
