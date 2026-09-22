<?php

namespace App\Telegram\Keyboards\AdminsKeyboard;

use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class DashboardKeyboard
{
    public static function make(bool $isSuperAdmin = false): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        // ═══════════════════════════════════════════════════════
        //  🏛️ الصف 1: الإدارة الأساسية
        // ═══════════════════════════════════════════════════════
        $keyboard->addRow(
            self::btn('👥 المستخدمون', 'admin.users', ButtonStyle::PRIMARY),
            self::btn('💰 الخزينة', 'admin.finance', ButtonStyle::SUCCESS),
        );

        // ═══════════════════════════════════════════════════════
        //  📊 الصف 2: التحليلات
        // ═══════════════════════════════════════════════════════
        $keyboard->addRow(
            self::btn('📊 الإحصائيات', 'admin.analytics', ButtonStyle::PRIMARY),
            self::btn('🤝 الإحالات', 'admin.referrals', ButtonStyle::PRIMARY),
        );

        // ═══════════════════════════════════════════════════════
        //  🎮 الصف 3: الترفيه
        // ═══════════════════════════════════════════════════════
        $keyboard->addRow(
            self::btn('🎡 عجلة الحظ', 'wheel.admin', ButtonStyle::SUCCESS),
            self::btn('🎁 الهدايا', 'admin.gift-codes', ButtonStyle::SUCCESS),
        );

        // ═══════════════════════════════════════════════════════
        //  ⚙️ الصف 4: الأدوات
        // ═══════════════════════════════════════════════════════
        $keyboard->addRow(
            self::btn('⚙️ النظام', 'admin.system', ButtonStyle::DANGER),
            self::btn('📢 التواصل', 'admin.communication', ButtonStyle::PRIMARY),
        );

        // ═══════════════════════════════════════════════════════
        //  🎧 الصف 5: الدعم
        // ═══════════════════════════════════════════════════════
        $keyboard->addRow(
            self::btn('🎧 خدمة العملاء', 'admin.support', ButtonStyle::SUCCESS),
        );

        return $keyboard;
    }

    /**
     * 🎨 Helper موحّد لإنشاء الأزرار
     */
    private static function btn(
        string $text,
        string $callback,
        ButtonStyle $style,
    ): InlineKeyboardButton {
        return InlineKeyboardButton::make(
            text: $text,
            callback_data: $callback,
            style: $style,
        );
    }
}
