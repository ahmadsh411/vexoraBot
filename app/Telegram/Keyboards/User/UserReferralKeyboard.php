<?php

namespace App\Telegram\Keyboards\User;

use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class UserReferralKeyboard
{
    // ============================================================
    //  🏠 القائمة الرئيسية
    // ============================================================
    public static function main(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()

            // ─── 🎯 عرض الإحالات ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🧑‍🤝‍🧑 قائمة المُحالين',
                    callback_data: 'user.referrals.list',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '🧾 سجل المكافآت',
                    callback_data: 'user.referrals.rewards.all',
                    style: ButtonStyle::SUCCESS,
                ),
            )

            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔙 رجوع للوحة',
                    callback_data: 'user.dashboard',
                ),
            );
    }

    // ============================================================
    //  🧾 سجل المكافآت — الفلاتر
    // ============================================================
    public static function rewards(string $filter = 'all'): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()

            // ─── 🏆 الفلاتر ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: $filter === 'all' ? '✅ الكل' : '📜 الكل',
                    callback_data: 'user.referrals.rewards.all',
                    style: $filter === 'all' ? ButtonStyle::SUCCESS : ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: $filter === 'instant' ? '✅ فوري' : '⚡ فوري',
                    callback_data: 'user.referrals.rewards.instant',
                    style: $filter === 'instant' ? ButtonStyle::SUCCESS : ButtonStyle::PRIMARY,
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: $filter === 'cycle' ? '✅ دوري' : '📅 دوري',
                    callback_data: 'user.referrals.rewards.cycle',
                    style: $filter === 'cycle' ? ButtonStyle::SUCCESS : ButtonStyle::PRIMARY,
                ),
            )

            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'user.referrals',
                ),
            );
    }

    // ============================================================
    //  ↩️ رجوع
    // ============================================================
    public static function back(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'user.referrals',
                ),
            );
    }
}
