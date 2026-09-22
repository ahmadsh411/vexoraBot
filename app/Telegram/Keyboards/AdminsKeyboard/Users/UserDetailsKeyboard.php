<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Users;

use App\Telegram\Keyboards\Base\BaseAdminKeyboard;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class UserDetailsKeyboard extends BaseAdminKeyboard
{
    public static function make(int $userId): InlineKeyboardMarkup
    {
        return self::build()
            // ═══════════════════════════════════════════════════
            //  الصف 1: تفعيل/إيقاف + تعديل
            // ═══════════════════════════════════════════════════
            ->pairButtons(
                '⚡ تفعيل / إيقاف',
                "admin.users.toggle.{$userId}",
                '✏️ تعديل البيانات',
                "admin.users.edit.{$userId}",
                ButtonStyle::PRIMARY,
                ButtonStyle::PRIMARY,
            )

            // ═══════════════════════════════════════════════════
            //  الصف 2: الرصيد
            // ═══════════════════════════════════════════════════
            ->fullButton(
                '💰 إدارة الرصيد',
                "admin.users.balance.{$userId}",
                ButtonStyle::SUCCESS,
            )

            // ═══════════════════════════════════════════════════
            //  الصف 3: الإحالات
            // ═══════════════════════════════════════════════════
            ->fullButton(
                '🧑‍🤝‍🧑 شبكة الإحالات',
                "admin.users.referrals.{$userId}",
                ButtonStyle::PRIMARY,
            )

            // ═══════════════════════════════════════════════════
            //  الصف 4: حذف
            // ═══════════════════════════════════════════════════
            ->fullButton(
                '🗑️ حذف المستخدم',
                "admin.users.delete.{$userId}",
                ButtonStyle::DANGER,
            )

            // ═══════════════════════════════════════════════════
            //  الصف 5: IChancy + تحديث
            // ═══════════════════════════════════════════════════
            ->pairButtons(
                '🎮 رصيد IChancy',
                "admin.users.ichancy.{$userId}",
                '🔄 تحديث',
                "admin.users.show.{$userId}",
                ButtonStyle::PRIMARY,
                ButtonStyle::PRIMARY,
            )

            // ═══════════════════════════════════════════════════
            //  الرجوع
            // ═══════════════════════════════════════════════════
            ->backButton('admin.users')
            ->toMarkup();
    }
}
