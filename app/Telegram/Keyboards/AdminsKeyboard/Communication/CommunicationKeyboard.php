<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Communication;

use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class CommunicationKeyboard
{
    // ============================================================
    //  🏠 القائمة الرئيسية
    // ============================================================
    public static function main(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()

            // ─── 📢 أدوات الإرسال ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📢 بث جماعي',
                    callback_data: 'comm.broadcast',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '✉️ رسالة لمستخدم',
                    callback_data: 'comm.single',
                    style: ButtonStyle::SUCCESS,
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
    //  📢 فئات البث
    // ============================================================
    public static function broadcastTargets(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()

            // ─── 👥 الكل ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '👥 الكل',
                    callback_data: 'comm.target.all',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── 💰 حسب الرصيد ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💰 رصيد NSP',
                    callback_data: 'comm.target.balance_nsp',
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '💵 رصيد USD',
                    callback_data: 'comm.target.balance_usd',
                    style: ButtonStyle::SUCCESS,
                ),
            )

            // ─── 🎯 حسب النشاط ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🎯 لديهم إحالات',
                    callback_data: 'comm.target.referrers',
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '🆕 الجدد (7 أيام)',
                    callback_data: 'comm.target.new',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── 😴 حسب الحالة ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '😴 الخاملون (30 يوم)',
                    callback_data: 'comm.target.inactive',
                    style: ButtonStyle::DANGER,
                ),
                InlineKeyboardButton::make(
                    text: '🏦 لديهم حساب دفع',
                    callback_data: 'comm.target.has_account',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── ✖️ إلغاء (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✖️ إلغاء',
                    callback_data: 'comm.cancel',
                ),
            );
    }

    // ============================================================
    //  ✅ تأكيد الإرسال
    // ============================================================
    public static function confirm(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()

            // ─── ✅ تأكيد ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✅ تأكيد الإرسال',
                    callback_data: 'comm.confirm',
                    style: ButtonStyle::SUCCESS,
                ),
            )

            // ─── ✖️ إلغاء (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✖️ إلغاء',
                    callback_data: 'comm.cancel',
                ),
            );
    }
}
