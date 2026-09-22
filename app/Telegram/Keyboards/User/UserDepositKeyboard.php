<?php

namespace App\Telegram\Keyboards\User;

use App\Models\Transaction;
use Illuminate\Support\Collection;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class UserDepositKeyboard
{
    // ============================================================
    //  📋 قائمة طرق الإيداع
    // ============================================================
    public static function methods(Collection $methods): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($methods as $method) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: $method->icon . ' ' . $method->name
                        . ' (' . $method->currency . ')',
                    callback_data: 'user.deposit.method.' . $method->id,
                    style: ButtonStyle::SUCCESS,
                ),
            );
        }

        // ─── ❌ إلغاء (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '❌ إلغاء',
                callback_data: 'user.deposit.cancel',
            ),
        );

        return $keyboard;
    }

    // ============================================================
    //  ↩️ رجوع للوحة
    // ============================================================
    public static function back(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'user.dashboard',
                ),
            );
    }

    // ============================================================
    //  ↩️ رجوع للطرق + ❌ إلغاء
    // ============================================================
    public static function backToMethods(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع للطرق',
                    callback_data: 'user.deposit',
                ),
                InlineKeyboardButton::make(
                    text: '❌ إلغاء',
                    callback_data: 'user.deposit.cancel',
                ),
            );
    }

    // ============================================================
    //  ✅ تأكيد
    // ============================================================
    public static function confirm(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✅ تأكيد',
                    callback_data: 'user.deposit.confirm',
                    style: ButtonStyle::SUCCESS,
                ),
            )
            // ─── ❌ إلغاء (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '❌ إلغاء',
                    callback_data: 'user.deposit.cancel',
                ),
            );
    }

    // ============================================================
    //  🎉 نجاح
    // ============================================================
    public static function success(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📜 السجل المالي',
                    callback_data: 'user.transactions',
                    style: ButtonStyle::PRIMARY,
                ),
            )
            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع للوحة',
                    callback_data: 'user.dashboard',
                ),
            );
    }

    // ============================================================
    //  👮 أزرار الأدمن (لإشعارات الإيداع)
    // ============================================================
    public static function adminActions(Transaction $transaction): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✅ موافقة',
                    callback_data: 'admin.finance.deposits.approve.' . $transaction->id,
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '❌ رفض',
                    callback_data: 'admin.finance.deposits.reject.' . $transaction->id,
                    style: ButtonStyle::DANGER,
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '👁️ عرض التفاصيل',
                    callback_data: 'admin.finance.deposits.show.' . $transaction->id,
                    style: ButtonStyle::PRIMARY,
                ),
            );
    }
}
