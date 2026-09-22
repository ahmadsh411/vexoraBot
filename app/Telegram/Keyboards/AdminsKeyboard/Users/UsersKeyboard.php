<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Users;

use App\Models\User;
use App\Telegram\Keyboards\Base\BaseAdminKeyboard;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class UsersKeyboard extends BaseAdminKeyboard
{
    public static function make(): InlineKeyboardMarkup
    {
        $unseenCount = User::whereNull('admin_seen_at')->count();

        $newLabel = $unseenCount > 0
            ? "✨ الجدد ({$unseenCount})"
            : '✨ الجدد';

        return self::build()
            // 📋 القائمة الكاملة (أزرق)
            ->fullButton(
                '📋 قائمة المستخدمين الكاملة',
                'admin.users.list',
                ButtonStyle::PRIMARY,
            )

            // ✨ الجدد + 🔍 بحث
            ->pairButtons(
                $newLabel,
                'admin.users.new',
                '🔍 بحث',
                'admin.users.search',
                ButtonStyle::PRIMARY,
                ButtonStyle::PRIMARY,
            )

            // 🗑 المحذوفون (أحمر)
            ->fullButton(
                '🗑️ المحذوفون',
                'admin.users.trashed',
                ButtonStyle::DANGER,
            )

            // ↩️ الرجوع
            ->backButton()
            ->toMarkup();
    }
}
