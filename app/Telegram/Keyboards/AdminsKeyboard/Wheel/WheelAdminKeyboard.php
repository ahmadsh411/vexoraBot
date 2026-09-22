<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Wheel;

use App\Models\Wheel;
use App\Models\WheelPrize;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class WheelAdminKeyboard
{
    // ============================================================
    //  🏠 القائمة الرئيسية
    // ============================================================

    public static function main(): InlineKeyboardMarkup
    {
        $wheel = Wheel::first();
        $isActive = $wheel?->is_active ?? false;

        return InlineKeyboardMarkup::make()
            // 🔵 الإعدادات + 🟢 الجوائز
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⚙️ الإعدادات',
                    callback_data: 'wheel.admin.settings',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '🎁 الجوائز',
                    callback_data: 'wheel.admin.prizes',
                    style: ButtonStyle::SUCCESS,
                ),
            )
            // 🔴 تعطيل / 🟢 تفعيل
            ->addRow(
                InlineKeyboardButton::make(
                    text: $isActive ? '🔴 تعطيل العجلة' : '🟢 تفعيل العجلة',
                    callback_data: 'wheel.admin.toggle-active',
                    style: $isActive ? ButtonStyle::DANGER : ButtonStyle::SUCCESS,
                ),
            )
            // 🔵 الإحصائيات + 📜 السجل
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📊 الإحصائيات',
                    callback_data: 'wheel.admin.stats',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '📜 السجل',
                    callback_data: 'wheel.admin.history.1',
                    style: ButtonStyle::PRIMARY,
                ),
            )
            // 🔵 لفات مستخدم
            ->addRow(
                InlineKeyboardButton::make(
                    text: '👤 لفات مستخدم',
                    callback_data: 'wheel.admin.user.search',
                    style: ButtonStyle::PRIMARY,
                ),
            )
            // ⚪ رجوع
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: 'admin.dashboard',
                ),
            );
    }

    // ============================================================
    //  ⚙️ الإعدادات
    // ============================================================

    public static function settings(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💰 قيمة اللفة NSP',
                    callback_data: 'wheel.admin.edit-setting.deposit_syp_threshold',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '💵 قيمة اللفة USD',
                    callback_data: 'wheel.admin.edit-setting.deposit_usd_threshold',
                    style: ButtonStyle::PRIMARY,
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📉 حد أدنى NSP',
                    callback_data: 'wheel.admin.edit-setting.min_deposit_syp_threshold',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '📈 حد أقصى NSP',
                    callback_data: 'wheel.admin.edit-setting.max_deposit_syp_threshold',
                    style: ButtonStyle::PRIMARY,
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔗 لفات الإحالة',
                    callback_data: 'wheel.admin.edit-setting.referral_threshold',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '📊 الحد اليومي',
                    callback_data: 'wheel.admin.edit-setting.daily_limit',
                    style: ButtonStyle::PRIMARY,
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📦 أقصى لفات مخزنة',
                    callback_data: 'wheel.admin.edit-setting.max_stored_spins',
                    style: ButtonStyle::PRIMARY,
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: 'wheel.admin',
                ),
            );
    }

    // ============================================================
    //  🎁 الجوائز
    // ============================================================

    public static function prizes(): InlineKeyboardMarkup
    {
        $wheel = Wheel::first();
        $keyboard = InlineKeyboardMarkup::make();

        if ($wheel) {
            $prizes = $wheel->prizes()->orderBy('sort_order')->get();

            foreach ($prizes as $prize) {
                $status = $prize->is_active ? '🟢' : '🔴';
                $id = (int) $prize->id;

                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        text: "{$status} {$prize->icon} {$prize->name} — {$prize->weight}%",
                        callback_data: "wheel.admin.prize.show.{$id}",
                        style: $prize->is_active ? ButtonStyle::SUCCESS : ButtonStyle::DANGER,
                    ),
                );
            }
        }

        // 🟢 إضافة جائزة
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '➕ إضافة جائزة',
                callback_data: 'wheel.admin.prize.create',
                style: ButtonStyle::SUCCESS,
            ),
        );

        // ⚪ رجوع
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '⬅️ رجوع',
                callback_data: 'wheel.admin',
            ),
        );

        return $keyboard;
    }

    // ============================================================
    //  🎁 تفاصيل جائزة
    // ============================================================

    public static function prizeDetails(WheelPrize $prize): InlineKeyboardMarkup
    {
        $id = (int) ($prize->id ?? 0);

        return InlineKeyboardMarkup::make()
            // 🔵 تعديل النسبة + البيانات
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🎯 تعديل النسبة',
                    callback_data: "wheel.admin.prize.weight.{$id}",
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '✏️ تعديل البيانات',
                    callback_data: "wheel.admin.prize.edit.{$id}",
                    style: ButtonStyle::PRIMARY,
                ),
            )
            // 🟢 تفعيل / 🔴 تعطيل + 🗑️ حذف
            ->addRow(
                InlineKeyboardButton::make(
                    text: $prize->is_active ? '🔴 تعطيل' : '🟢 تفعيل',
                    callback_data: "wheel.admin.prize.toggle.{$id}",
                    style: $prize->is_active ? ButtonStyle::DANGER : ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '🗑️ حذف',
                    callback_data: "wheel.admin.prize.delete.{$id}",
                    style: ButtonStyle::DANGER,
                ),
            )
            // ⚪ رجوع
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: 'wheel.admin.prizes',
                ),
            );
    }

    // ============================================================
    //  🗑️ تأكيد الحذف
    // ============================================================

    public static function confirmDeletePrize(WheelPrize $prize): InlineKeyboardMarkup
    {
        $id = (int) ($prize->id ?? 0);

        return InlineKeyboardMarkup::make()
            ->addRow(
                // 🔴 نعم احذف
                InlineKeyboardButton::make(
                    text: '✅ نعم، احذف',
                    callback_data: "wheel.admin.prize.delete-confirm.{$id}",
                    style: ButtonStyle::DANGER,
                ),
                // ⚪ إلغاء
                InlineKeyboardButton::make(
                    text: '❌ إلغاء',
                    callback_data: "wheel.admin.prize.show.{$id}",
                ),
            );
    }

    // ============================================================
    //  📊 الإحصائيات
    // ============================================================

    public static function stats(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                // 🔵 تحديث
                InlineKeyboardButton::make(
                    text: '🔄 تحديث',
                    callback_data: 'wheel.admin.stats',
                    style: ButtonStyle::PRIMARY,
                ),
                // ⚪ رجوع
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: 'wheel.admin',
                ),
            );
    }

    // ============================================================
    //  📜 السجل
    // ============================================================

    public static function history(int $page = 1): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        $navRow = [];

        if ($page > 1) {
            $navRow[] = InlineKeyboardButton::make(
                text: '◀️ السابق',
                callback_data: 'wheel.admin.history.' . ($page - 1),
                style: ButtonStyle::PRIMARY,
            );
        }

        $navRow[] = InlineKeyboardButton::make(
            text: "📄 {$page}",
            callback_data: 'wheel.admin.history.noop',
        );

        $navRow[] = InlineKeyboardButton::make(
            text: 'التالي ▶️',
            callback_data: 'wheel.admin.history.' . ($page + 1),
            style: ButtonStyle::PRIMARY,
        );

        $keyboard->addRow(...$navRow);

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '⬅️ رجوع',
                callback_data: 'wheel.admin',
            ),
        );

        return $keyboard;
    }
}
