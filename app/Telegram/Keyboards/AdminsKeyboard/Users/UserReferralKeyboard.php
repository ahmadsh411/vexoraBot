<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Users;

use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class UserReferralKeyboard
{
    public static function make(int $userId): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            // ═══════════════════════════════════════════════════
            //  البيانات الأساسية
            // ═══════════════════════════════════════════════════
            ->addRow(
                InlineKeyboardButton::make(
                    text: '👥 قائمة المُحالين',
                    callback_data: "admin.users.referrals.list.{$userId}",
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '🧾 سجل المكافآت',
                    callback_data: "admin.users.referrals.rewards.{$userId}",
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ═══════════════════════════════════════════════════
            //  أنواع المكافآت
            // ═══════════════════════════════════════════════════
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⚡ الفورية',
                    callback_data: "admin.users.referrals.instant.{$userId}",
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '📅 الدورات',
                    callback_data: "admin.users.referrals.cycles.{$userId}",
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ═══════════════════════════════════════════════════
            //  إعادة تعيين النوع
            // ═══════════════════════════════════════════════════
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔃 إعادة تعيين النوع',
                    callback_data: "admin.users.referrals.reset-type.{$userId}",
                    style: ButtonStyle::DANGER,
                ),
            )

            // ═══════════════════════════════════════════════════
            //  الرجوع
            // ═══════════════════════════════════════════════════
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: "admin.users.show.{$userId}",
                ),
            );
    }

    public static function referralsList(int $userId): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: "admin.users.referrals.{$userId}",
                ),
            );
    }

    public static function rewardsList(int $userId): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: "admin.users.referrals.{$userId}",
                ),
            );
    }

    public static function confirmResetType(int $userId): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            // 🔴 تأكيد
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✅ نعم، أعِد التعيين',
                    callback_data: "admin.users.referrals.reset-type-confirm.{$userId}",
                    style: ButtonStyle::DANGER,
                ),
                // ⚪ إلغاء
                InlineKeyboardButton::make(
                    text: '✖️ إلغاء',
                    callback_data: "admin.users.referrals.{$userId}",
                ),
            );
    }
}
