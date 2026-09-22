<?php

namespace App\Telegram\Screens\Admin\Users;

use App\Models\User;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class TrashedUsersScreen
{
    public static function listText(): string
    {
        $count = User::onlyTrashed()->count();

        if ($count === 0) {
            return implode("\n", [
                '🗑️ <b>المستخدمون المحذوفون</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                'لا يوجد مستخدمون محذوفون حالياً.',
            ]);
        }

        return implode("\n", [
            '🗑️ <b>المستخدمون المحذوفون</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👥 العدد الإجمالي: <b>' . $count . '</b>',
            '',
            'اختر مستخدماً لعرض تفاصيله:',
        ]);
    }

    public static function listKeyboard(): InlineKeyboardMarkup
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

            $daysAgo = $user->deleted_at?->diffInDays(now()) ?? 0;
            $timeLabel = $daysAgo === 0 ? 'اليوم' : "قبل {$daysAgo} يوم";

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🗑️ ' . $name . ' — ' . $timeLabel,
                    callback_data: "admin.users.trashed.show.{$user->id}",
                ),
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '♻️ استعادة الكل (' . $users->count() . ')',
                callback_data: 'admin.users.trashed.restore-all',
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

    public static function detailsText(User $user): string
    {
        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        if ($name === '') {
            $name = $user->username;
        }

        return implode("\n", [
            '🗑️ <b>تفاصيل مستخدم محذوف</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👤 <b>الاسم:</b> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            '🆔 <b>المعرف:</b> <code>' . $user->id . '</code>',
            '📛 <b>اسم المستخدم:</b> <code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
            '📱 <b>Telegram ID:</b> <code>' . $user->telegram_id . '</code>',
            '🎮 <b>Ichancy ID:</b> <code>' . ($user->ichancy_player_id ?? '-') . '</code>',
            '🟢 <b>الحالة قبل الحذف:</b> ' . ($user->is_active ? 'نشط' : 'موقوف'),
            '',
            '📅 <b>تاريخ التسجيل:</b> ' . $user->created_at?->format('Y-m-d H:i'),
            '🗑️ <b>تاريخ الحذف:</b> ' . $user->deleted_at?->format('Y-m-d H:i'),
        ]);
    }

    public static function detailsKeyboard(User $user): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '♻️ استعادة',
                    callback_data: "admin.users.trashed.restore.{$user->id}",
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💥 حذف نهائي',
                    callback_data: "admin.users.trashed.force.{$user->id}",
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: 'admin.users.trashed',
                ),
            );
    }

    public static function confirmForceText(User $user): string
    {
        return implode("\n", [
            '💥 <b>تأكيد الحذف النهائي</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '⚠️ <b>تحذير:</b> لا يمكن التراجع!',
            '',
            '👤 <b>المستخدم:</b> ' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8'),
            '🆔 <b>المعرف:</b> <code>' . $user->id . '</code>',
        ]);
    }

    public static function confirmForceKeyboard(User $user): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💥 نعم، احذف نهائياً',
                    callback_data: "admin.users.trashed.force-confirm.{$user->id}",
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '❌ إلغاء',
                    callback_data: "admin.users.trashed.show.{$user->id}",
                ),
            );
    }
}
