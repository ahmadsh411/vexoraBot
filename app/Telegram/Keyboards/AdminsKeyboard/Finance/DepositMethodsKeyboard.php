<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Finance;

use App\Models\DepositMethod;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class DepositMethodsKeyboard
{
    // ============================================================
    //  📋 قائمة الطرق
    // ============================================================
    public static function list(): InlineKeyboardMarkup
    {
        $methods = DepositMethod::ordered()->get();
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($methods as $method) {
            $status = $method->is_active ? '🟢' : '🔴';
            $style = $method->is_active
                ? ButtonStyle::SUCCESS
                : ButtonStyle::DANGER;

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: "{$status} {$method->icon} {$method->name}",
                    callback_data: "admin.finance.deposit-methods.show.{$method->id}",
                    style: $style,
                ),
            );
        }

        // ─── ➕ إضافة ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '➕ إضافة طريقة جديدة',
                callback_data: 'admin.finance.deposit-methods.create',
                style: ButtonStyle::SUCCESS,
            ),
        );

        // ─── ↩️ رجوع (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '↩️ رجوع',
                callback_data: 'admin.finance',
            ),
        );

        return $keyboard;
    }

    // ============================================================
    //  👁️ تفاصيل طريقة
    // ============================================================
    public static function details(DepositMethod $method): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()

            // ─── ✏️ البيانات الأساسية ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✏️ الاسم',
                    callback_data: "admin.finance.deposit-methods.edit-name.{$method->id}",
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '🎨 الأيقونة',
                    callback_data: "admin.finance.deposit-methods.edit-icon.{$method->id}",
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── 👤 بيانات الحساب ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📞 رقم الحساب',
                    callback_data: "admin.finance.deposit-methods.edit-account.{$method->id}",
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '👤 اسم الحساب',
                    callback_data: "admin.finance.deposit-methods.edit-account-name.{$method->id}",
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── ✅ التحقق التلقائي ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📱 GSM (سيرياتيل)',
                    callback_data: "admin.finance.deposit-methods.edit-receiver-gsm.{$method->id}",
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '🏦 عنوان شام كاش',
                    callback_data: "admin.finance.deposit-methods.edit-receiver-address.{$method->id}",
                    style: ButtonStyle::SUCCESS,
                ),
            )

            // ─── ⚙️ الإعدادات ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📊 الحدود',
                    callback_data: "admin.finance.deposit-methods.edit-limits.{$method->id}",
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '💼 العمولة',
                    callback_data: "admin.finance.deposit-methods.edit-commission.{$method->id}",
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── 📝 التعليمات ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📝 تعديل التعليمات',
                    callback_data: "admin.finance.deposit-methods.edit-instructions.{$method->id}",
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── 🔧 الحالة + 🗑️ الحذف ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: $method->is_active ? '🔴 تعطيل' : '🟢 تفعيل',
                    callback_data: "admin.finance.deposit-methods.toggle.{$method->id}",
                    style: $method->is_active ? ButtonStyle::DANGER : ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '🗑️ حذف',
                    callback_data: "admin.finance.deposit-methods.delete.{$method->id}",
                    style: ButtonStyle::DANGER,
                ),
            )

            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'admin.finance.deposit-methods',
                ),
            );
    }

    // ============================================================
    //  ⚠️ تأكيد الحذف
    // ============================================================
    public static function confirmDelete(DepositMethod $method): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✅ نعم، احذف',
                    callback_data: "admin.finance.deposit-methods.delete-confirm.{$method->id}",
                    style: ButtonStyle::DANGER,
                ),
                InlineKeyboardButton::make(
                    text: '✖️ إلغاء',
                    callback_data: "admin.finance.deposit-methods.show.{$method->id}",
                ),
            );
    }

    // ============================================================
    //  💱 اختيار العملة
    // ============================================================
    public static function currencyKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()

            // ─── 💰 NSP ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💰 ليرة سورية (NSP)',
                    callback_data: 'admin.dm.create.currency.NSP',
                    style: ButtonStyle::SUCCESS,
                ),
            )

            // ─── 💵 USD ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🇺🇸 دولار أمريكي (USD)',
                    callback_data: 'admin.dm.create.currency.USD',
                    style: ButtonStyle::SUCCESS,
                ),
            )

            // ─── ✖️ إلغاء (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✖️ إلغاء',
                    callback_data: 'admin.finance.deposit-methods',
                ),
            );
    }
}
