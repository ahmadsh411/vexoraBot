<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\System;

use App\Models\User;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class AdminKeyboard
{
    // ============================================================
    //  🏠 القائمة الرئيسية
    // ============================================================
    public static function main(): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        $admins = User::where('is_admin', true)
            ->orderByDesc('is_super_admin')
            ->orderBy('id')
            ->get();

        // ─── قائمة الأدمن ───
        if ($admins->isEmpty()) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '📭 لا يوجد أدمن',
                    callback_data: 'sys.admin.noop',
                ),
            );
        } else {
            foreach ($admins as $admin) {
                $icon = $admin->is_super_admin ? '👑' : '🛡️';

                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        text: "{$icon} {$admin->username}",
                        callback_data: 'sys.admin.show.' . $admin->id,
                        style: ButtonStyle::PRIMARY,
                    ),
                );
            }
        }

        // ─── إضافة أدمن ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '➕ إضافة أدمن',
                callback_data: 'sys.admin.create',
                style: ButtonStyle::SUCCESS,
            ),
        );

        // ─── رجوع (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '↩️ رجوع',
                callback_data: 'admin.system',
            ),
        );

        return $keyboard;
    }

    // ============================================================
    //  👤 تفاصيل أدمن
    // ============================================================
    public static function adminDetails(User $admin, bool $isCurrentUserSuper): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        if ($isCurrentUserSuper) {
            $canPromote = ! $admin->is_super_admin || User::where('is_admin', true)
                ->where('is_super_admin', true)
                ->count() > 1;

            if ($canPromote) {
                $label = $admin->is_super_admin
                    ? '🛡️ تخفيض لأدمن'
                    : '👑 ترقية لمشرف';

                $style = $admin->is_super_admin
                    ? ButtonStyle::DANGER
                    : ButtonStyle::SUCCESS;

                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        text: $label,
                        callback_data: 'sys.admin.promote.' . $admin->id,
                        style: $style,
                    ),
                );
            } else {
                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        text: '⚠️ لا يمكن تعديل آخر مشرف',
                        callback_data: 'sys.admin.noop',
                    ),
                );
            }

            // ─── حذف ───
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🗑️ حذف الأدمن',
                    callback_data: 'sys.admin.delete.' . $admin->id,
                    style: ButtonStyle::DANGER,
                ),
            );
        }

        // ─── رجوع (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '↩️ رجوع',
                callback_data: 'sys.admins',
            ),
        );

        return $keyboard;
    }

    // ============================================================
    //  ⚠️ تأكيد الحذف
    // ============================================================
    public static function confirmDelete(User $admin): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✅ نعم، احذف',
                    callback_data: 'sys.admin.delete-confirm.' . $admin->id,
                    style: ButtonStyle::DANGER,
                ),
                InlineKeyboardButton::make(
                    text: '✖️ إلغاء',
                    callback_data: 'sys.admin.show.' . $admin->id,
                ),
            );
    }
}
