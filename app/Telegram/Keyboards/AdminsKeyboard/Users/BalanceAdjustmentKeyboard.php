<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Users;

use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class BalanceAdjustmentKeyboard
{
    /**
     * كيبورد القائمة الرئيسية.
     */
    public static function make(int $userId): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            // 🟢 إضافة رصيد
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💰 إضافة رصيد',
                    callback_data: "admin.users.balance.add.{$userId}",
                    style: ButtonStyle::SUCCESS,
                ),
            )
            // 🔴 خصم رصيد
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💸 خصم رصيد',
                    callback_data: "admin.users.balance.sub.{$userId}",
                    style: ButtonStyle::DANGER,
                ),
            )
            // 🔵 سجل التعديلات
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📋 سجل التعديلات',
                    callback_data: "admin.users.balance.history.{$userId}",
                    style: ButtonStyle::PRIMARY,
                ),
            )
            // ⚪ رجوع
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔙 رجوع',
                    callback_data: "admin.users.show.{$userId}",
                ),
            );
    }

    /**
     * كيبورد اختيار العملة.
     */
    public static function currencyKeyboard(string $action, int $userId): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            // 🔵 NSP
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💰 ليرة سورية (NSP)',
                    callback_data: "admin.balance.currency.{$action}.{$userId}.NSP",
                    style: ButtonStyle::PRIMARY,
                ),
            )
            // 🔵 USD
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🇺🇸 دولار أمريكي (USD)',
                    callback_data: "admin.balance.currency.{$action}.{$userId}.USD",
                    style: ButtonStyle::PRIMARY,
                ),
            )
            // ⚪ رجوع
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔙 رجوع',
                    callback_data: "admin.users.balance.{$userId}",
                ),
            );
    }
}
