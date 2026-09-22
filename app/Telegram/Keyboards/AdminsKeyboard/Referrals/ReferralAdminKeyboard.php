<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Referrals;

use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ReferralAdminKeyboard
{
    public static function make(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()

            // ─── إعدادات النسب ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⚙️ إعدادات النسب',
                    callback_data: 'admin.referrals.settings',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── إدارة الدورات ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📅 إدارة الدورات',
                    callback_data: 'admin.referrals.cycles',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── الأفضل + الكل ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🏆 الأفضل',
                    callback_data: 'admin.referrals.top',
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '👥 الكل',
                    callback_data: 'admin.referrals.all',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── سجل المكافآت ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📜 سجل المكافآت',
                    callback_data: 'admin.referrals.history',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'admin.dashboard',
                ),
            );
    }
}
