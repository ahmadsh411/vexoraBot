<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Analytics;

use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class AnalyticsKeyboard
{
    // ============================================================
    //  🏠 القائمة الرئيسية
    // ============================================================
    public static function make(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()

            // ─── 📊 التقارير الأساسية ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '👥 المستخدمون',
                    callback_data: 'admin.analytics.users',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '💰 المالية',
                    callback_data: 'admin.analytics.finance',
                    style: ButtonStyle::SUCCESS,
                ),
            )

            // ─── 🎯 التحليلات المتقدمة ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🎯 الإحالات',
                    callback_data: 'admin.analytics.referrals',
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '📈 المقارنات',
                    callback_data: 'admin.analytics.comparison',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'admin.dashboard',
                ),
            );
    }

    // ============================================================
    //  👥 كيبورد المستخدمين
    // ============================================================
    public static function users(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔄 تحديث',
                    callback_data: 'admin.analytics.users',
                    style: ButtonStyle::PRIMARY,
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'admin.analytics',
                ),
            );
    }

    // ============================================================
    //  💰 كيبورد المالية
    // ============================================================
    public static function finance(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔄 تحديث',
                    callback_data: 'admin.analytics.finance',
                    style: ButtonStyle::SUCCESS,
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'admin.analytics',
                ),
            );
    }

    // ============================================================
    //  🎯 كيبورد الإحالات
    // ============================================================
    public static function referrals(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔄 تحديث',
                    callback_data: 'admin.analytics.referrals',
                    style: ButtonStyle::SUCCESS,
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'admin.analytics',
                ),
            );
    }

    // ============================================================
    //  📈 كيبورد المقارنات
    // ============================================================
    public static function comparison(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()

            // ─── 📅 فترات قصيرة ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📅 يوم vs يوم',
                    callback_data: 'admin.analytics.comparison.day',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '📆 أسبوع vs أسبوع',
                    callback_data: 'admin.analytics.comparison.week',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── 🗓️ فترات طويلة ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🗓️ شهر vs شهر',
                    callback_data: 'admin.analytics.comparison.month',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'admin.analytics',
                ),
            );
    }
}
