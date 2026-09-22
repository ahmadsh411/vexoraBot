<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\System;

use App\Models\Setting;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class SystemKeyboard
{
    // ============================================================
    //  🏠 القائمة الرئيسية
    // ============================================================
    public static function main(bool $isSuperAdmin = false): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        // ═══════════════════════════════════════════════════════
        //  🔷 الإدارة الأساسية
        // ═══════════════════════════════════════════════════════
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '📝 الإعدادات العامة',
                callback_data: 'sys.general',
                style: ButtonStyle::PRIMARY,
            ),
            InlineKeyboardButton::make(
                text: '💰 الإعدادات المالية',
                callback_data: 'sys.finance',
                style: ButtonStyle::SUCCESS,
            ),
        );

        // ═══════════════════════════════════════════════════════
        //  🔧 الصيانة + 👑 الأدمن
        // ═══════════════════════════════════════════════════════
        if ($isSuperAdmin) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🔧 الصيانة',
                    callback_data: 'sys.maintenance',
                    style: ButtonStyle::DANGER,
                ),
                InlineKeyboardButton::make(
                    text: '👑 إدارة الأدمن',
                    callback_data: 'sys.admins',
                    style: ButtonStyle::SUCCESS,
                ),
            );
        } else {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🔧 الصيانة',
                    callback_data: 'sys.maintenance',
                    style: ButtonStyle::DANGER,
                ),
            );
        }

        // ═══════════════════════════════════════════════════════
        //  🎮 الأنظمة
        // ═══════════════════════════════════════════════════════
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '🎮 إعدادات IChancy',
                callback_data: 'sys.ichancy',
                style: ButtonStyle::PRIMARY,
            ),
            InlineKeyboardButton::make(
                text: '💱 أسعار الصرف',
                callback_data: 'admin.exchange_rates',
                style: ButtonStyle::PRIMARY,
            ),
        );

        // ═══════════════════════════════════════════════════════
        //  🎁 المكافآت
        // ═══════════════════════════════════════════════════════
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '🎁 مكافآت الإيداع',
                callback_data: 'admin.deposit_bonus',
                style: ButtonStyle::SUCCESS,
            ),
            InlineKeyboardButton::make(
                text: '🎁 مكافأة التسجيل',
                callback_data: 'admin.signup_bonus',
                style: ButtonStyle::SUCCESS,
            ),
        );

        // ═══════════════════════════════════════════════════════
        //  💎 الجواهر + 📊 معلومات
        // ═══════════════════════════════════════════════════════
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '💎 نظام الجواهر',
                callback_data: 'admin.gems',
                style: ButtonStyle::SUCCESS,
            ),
            InlineKeyboardButton::make(
                text: '📊 معلومات النظام',
                callback_data: 'sys.info',
                style: ButtonStyle::PRIMARY,
            ),
        );

        // ═══════════════════════════════════════════════════════
        //  ↩️ رجوع (بدون لون)
        // ═══════════════════════════════════════════════════════
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '↩️ رجوع',
                callback_data: 'admin.dashboard',
            ),
        );

        return $keyboard;
    }

    // ============================================================
    //  📂 أزرار المجموعة
    // ============================================================
    public static function forGroup(string $group): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();
        $settings = Setting::byGroup($group);

        foreach ($settings as $setting) {
            if (! $setting->is_editable) {
                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        text: '🔒 ' . ($setting->label ?? $setting->key)
                            . ' — ' . $setting->display_value,
                        callback_data: 'sys.noop',
                    ),
                );
                continue;
            }

            if ($setting->type === 'bool') {
                $icon = $setting->typed_value ? '🟢' : '🔴';
                $style = $setting->typed_value
                    ? ButtonStyle::SUCCESS
                    : ButtonStyle::DANGER;

                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        text: $icon . ' ' . ($setting->label ?? $setting->key),
                        callback_data: 'sys.toggle.' . $setting->key,
                        style: $style,
                    ),
                );
                continue;
            }

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '✏️ ' . ($setting->label ?? $setting->key)
                        . ' — ' . $setting->display_value,
                    callback_data: 'sys.edit.' . $setting->key,
                    style: ButtonStyle::PRIMARY,
                ),
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '↩️ رجوع',
                callback_data: 'admin.system',
            ),
        );

        return $keyboard;
    }

    // ============================================================
    //  🔧 الصيانة
    // ============================================================
    public static function maintenance(bool $enabled): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: $enabled ? '🟢 إيقاف الصيانة' : '🔴 تفعيل الصيانة',
                    callback_data: $enabled
                        ? 'sys.maintenance.confirm-off'
                        : 'sys.maintenance.confirm-on',
                    style: $enabled ? ButtonStyle::SUCCESS : ButtonStyle::DANGER,
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✏️ تعديل رسالة الصيانة',
                    callback_data: 'sys.edit.maintenance_message',
                    style: ButtonStyle::PRIMARY,
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'admin.system',
                ),
            );
    }

    // ============================================================
    //  ⚠️ تأكيد الصيانة
    // ============================================================
    public static function confirmMaintenance(bool $enable): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✅ نعم، تأكيد',
                    callback_data: $enable
                        ? 'sys.maintenance.do-on'
                        : 'sys.maintenance.do-off',
                    style: $enable ? ButtonStyle::DANGER : ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '✖️ إلغاء',
                    callback_data: 'sys.maintenance',
                ),
            );
    }

    // ============================================================
    //  ⚠️ تأكيد عام
    // ============================================================
    public static function confirm(string $action): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✅ تأكيد',
                    callback_data: 'sys.confirm.' . $action,
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '✖️ إلغاء',
                    callback_data: 'sys.cancel',
                ),
            );
    }
}
