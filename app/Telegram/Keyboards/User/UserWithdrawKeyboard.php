<?php

namespace App\Telegram\Keyboards\User;

use App\Models\DepositMethod;
use Illuminate\Support\Collection;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class UserWithdrawKeyboard
{
    // ============================================================
    //  📋 طرق السحب
    // ============================================================
    public static function methods(Collection $methods): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($methods as $method) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: $method->icon . ' ' . $method->name . ' (' . $method->currency . ')',
                    callback_data: 'user.withdraw.method.' . $method->id,
                    style: ButtonStyle::PRIMARY,
                ),
            );
        }

        // ─── ❌ إلغاء (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '❌ إلغاء',
                callback_data: 'user.withdraw.cancel',
            ),
        );

        return $keyboard;
    }

    // ============================================================
    //  💼 قائمة الحسابات
    // ============================================================
    public static function accounts(DepositMethod $method, Collection $accounts): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($accounts as $account) {
            $label = $account->account_number;

            if ($account->account_name) {
                $label .= ' — ' . $account->account_name;
            }

            if (mb_strlen($label) > 40) {
                $label = mb_substr($label, 0, 40) . '…';
            }

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '💼 ' . $label,
                    callback_data: 'user.withdraw.account.' . $account->id,
                    style: ButtonStyle::PRIMARY,
                ),
            );
        }

        // ─── ➕ إضافة حساب جديد ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '➕ إضافة حساب جديد',
                callback_data: 'user.withdraw.new-account',
                style: ButtonStyle::SUCCESS,
            ),
        );

        // ─── ↩️ رجوع + ❌ إلغاء (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '↩️ رجوع للطرق',
                callback_data: 'user.withdraw',
            ),
            InlineKeyboardButton::make(
                text: '❌ إلغاء',
                callback_data: 'user.withdraw.cancel',
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
                    callback_data: 'user.withdraw',
                ),
                InlineKeyboardButton::make(
                    text: '❌ إلغاء',
                    callback_data: 'user.withdraw.cancel',
                ),
            );
    }

    // ============================================================
    //  ✅ تأكيد السحب
    // ============================================================
    public static function confirm(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✅ تأكيد السحب',
                    callback_data: 'user.withdraw.confirm',
                    style: ButtonStyle::SUCCESS,
                ),
            )
            // ─── ❌ إلغاء (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '❌ إلغاء',
                    callback_data: 'user.withdraw.cancel',
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
}
