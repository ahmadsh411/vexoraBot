<?php

namespace App\Telegram\Screens\Admin\Finance;

use App\Models\DepositMethod;
use App\Models\WithdrawMethod;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class MethodsScreen
{
    /**
     * قائمة طرق الإيداع.
     */
    public static function depositListText(): string
    {
        $methods = DepositMethod::ordered()->get();

        if ($methods->isEmpty()) {
            return implode("\n", [
                '📥 <b>طرق الإيداع</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '❌ لا توجد طرق.',
            ]);
        }

        $lines = [
            '📥 <b>طرق الإيداع</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 العدد: <b>' . $methods->count() . '</b>',
            '',
        ];

        foreach ($methods as $method) {
            $status = $method->is_active ? '🟢' : '🔴';
            $lines[] = "{$status} {$method->full_name}";
            $lines[] = "   📞 <code>" . ($method->account_number ?: 'غير محدد') . "</code>";
            $lines[] = "   📊 " . $method->limits_label;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    public static function depositListKeyboard(): InlineKeyboardMarkup
    {
        $methods = DepositMethod::ordered()->get();
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($methods as $method) {
            $status = $method->is_active ? '🟢' : '🔴';
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: "{$status} {$method->icon} {$method->name}",
                    callback_data: "admin.finance.deposit-methods.show.{$method->id}",
                ),
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '➕ إضافة طريقة',
                callback_data: 'admin.finance.deposit-methods.create',
            ),
        );

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '⬅️ رجوع',
                callback_data: 'admin.finance',
            ),
        );

        return $keyboard;
    }

    /**
     * تفاصيل طريقة إيداع.
     */
    public static function depositDetailsText(DepositMethod $method): string
    {
        return implode("\n", [
            $method->icon . ' <b>' . $method->name . '</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الحالة:</b> ' . $method->status_label,
            '💰 <b>العملة:</b> ' . $method->currency_icon . ' ' . $method->currency,
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📞 <b>رقم الحساب:</b>',
            '<code>' . ($method->account_number ?: 'غير محدد') . '</code>',
            '',
            '👤 <b>اسم الحساب:</b>',
            '<code>' . ($method->account_name ?: 'غير محدد') . '</code>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الحدود:</b> ' . $method->limits_label,
            '💼 <b>العمولة:</b> ' . $method->commission_percent . '%',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📝 <b>التعليمات:</b>',
            $method->instructions ?: '-',
        ]);
    }

    public static function depositDetailsKeyboard(DepositMethod $method): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '✏️ تعديل الرقم',
                callback_data: "admin.finance.deposit-methods.edit-account.{$method->id}",
            ),
        );

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '📝 تعديل التعليمات',
                callback_data: "admin.finance.deposit-methods.edit-instructions.{$method->id}",
            ),
        );

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '📊 تعديل الحدود',
                callback_data: "admin.finance.deposit-methods.edit-limits.{$method->id}",
            ),
        );

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: $method->is_active ? '🔴 تعطيل' : '🟢 تفعيل',
                callback_data: "admin.finance.deposit-methods.toggle.{$method->id}",
            ),
            InlineKeyboardButton::make(
                text: '🗑️ حذف',
                callback_data: "admin.finance.deposit-methods.delete.{$method->id}",
            ),
        );

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '⬅️ رجوع',
                callback_data: 'admin.finance.deposit-methods',
            ),
        );

        return $keyboard;
    }
}
