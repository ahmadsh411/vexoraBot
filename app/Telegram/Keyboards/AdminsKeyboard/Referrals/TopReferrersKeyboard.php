<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Referrals;

use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class TopReferrersKeyboard
{
    public static function make(string $period = 'all'): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()

            // ─── الكل + الشهر ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: $period === 'all' ? '✅ الكل' : '🏆 الكل',
                    callback_data: 'admin.referrals.top.all',
                    style: $period === 'all' ? ButtonStyle::SUCCESS : ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: $period === 'month' ? '✅ الشهر' : '📅 الشهر',
                    callback_data: 'admin.referrals.top.month',
                    style: $period === 'month' ? ButtonStyle::SUCCESS : ButtonStyle::PRIMARY,
                ),
            )

            // ─── الأسبوع + اليوم ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: $period === 'week' ? '✅ الأسبوع' : '📆 الأسبوع',
                    callback_data: 'admin.referrals.top.week',
                    style: $period === 'week' ? ButtonStyle::SUCCESS : ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: $period === 'today' ? '✅ اليوم' : '🗓️ اليوم',
                    callback_data: 'admin.referrals.top.today',
                    style: $period === 'today' ? ButtonStyle::SUCCESS : ButtonStyle::PRIMARY,
                ),
            )

            // ─── رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'admin.referrals',
                ),
            );
    }
}
