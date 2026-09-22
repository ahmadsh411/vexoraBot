<?php

namespace App\Telegram\Keyboards\User;

use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class UserDashboardKeyboard
{
    public static function main(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()

            // ═══════════════════════════════════════════════════
            //  💼 المحفظة — الإجراءات المالية
            // ═══════════════════════════════════════════════════
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💰 شحن المحفظة',
                    callback_data: 'user.deposit',
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '📤 سحب',
                    callback_data: 'user.withdraw',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ═══════════════════════════════════════════════════
            //  🎮 IChancy — التكامل مع المنصة
            // ═══════════════════════════════════════════════════
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🎮 شحن IChancy',
                    callback_data: 'user.ichancy.deposit',
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '💸 سحب IChancy',
                    callback_data: 'user.ichancy.withdraw',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ═══════════════════════════════════════════════════
            //  👤 الحساب — المعلومات الشخصية
            // ═══════════════════════════════════════════════════
            ->addRow(
                InlineKeyboardButton::make(
                    text: '👤 حسابي',
                    callback_data: 'user.profile',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '📜 السجل المالي',
                    callback_data: 'user.transactions',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ═══════════════════════════════════════════════════
            //  🎁 المكافآت — الترفيه والمكافآت
            // ═══════════════════════════════════════════════════
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🎡 عجلة الحظ',
                    callback_data: 'user.wheel',
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '🤝 الإحالات',
                    callback_data: 'user.referrals',
                    style: ButtonStyle::SUCCESS,
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💎 جواهري',
                    callback_data: 'user.gems',
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '🎁 كود هدية',
                    callback_data: 'user.gift',
                    style: ButtonStyle::SUCCESS,
                ),
            )

            // ═══════════════════════════════════════════════════
            //  🆘 المساعدة — الدعم والمساعدة
            // ═══════════════════════════════════════════════════
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📞 الدعم',
                    callback_data: 'user.support',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '📖 الدليل',
                    callback_data: 'user.guide',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ═══════════════════════════════════════════════════
            //  📋 معلومات — الموقع والشروط والإعدادات
            // ═══════════════════════════════════════════════════
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🌐 الموقع',
                    url: config('services.website.url') ?: 'https://vexora.com',
                ),
                InlineKeyboardButton::make(
                    text: '📋 الشروط',
                    callback_data: 'user.terms',
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⚙️ إعدادات الحساب',
                    callback_data: 'user.settings',
                    style: ButtonStyle::PRIMARY,
                ),
            );
    }

    // ============================================================
    //  ⚙️ قائمة الإعدادات
    // ============================================================
    public static function settings(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            // ─── 🗑 حذف الحساب ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🗑 حذف الحساب',
                    callback_data: 'user.delete-account',
                    style: ButtonStyle::DANGER,
                ),
            )
            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'user.dashboard',
                ),
            );
    }
}
