<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\GiftCode;

use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class GiftCodesKeyboard
{
    // ============================================================
    //  🏠 القائمة الرئيسية
    // ============================================================
    public static function main(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()

            // ─── ➕ إنشاء كود ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '➕ إنشاء كود جديد',
                    callback_data: 'admin.gift-codes.create',
                    style: ButtonStyle::SUCCESS,
                ),
            )

            // ─── 📋 الأكواد النشطة + 📜 السجل ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📋 الأكواد النشطة',
                    callback_data: 'admin.gift-codes.active',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '📜 السجل',
                    callback_data: 'admin.gift-codes.history',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── 🔙 رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔙 رجوع',
                    callback_data: 'admin.dashboard',
                ),
            );
    }

    // ============================================================
    //  📋 قائمة الأكواد النشطة
    // ============================================================
    public static function activeList($codes): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($codes as $code) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🚫 تعطيل ' . $code->code,
                    callback_data: 'admin.gift-codes.disable.' . $code->id,
                    style: ButtonStyle::DANGER,
                ),
            );
        }

        // ─── ➕ إنشاء كود ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '➕ إنشاء كود جديد',
                callback_data: 'admin.gift-codes.create',
                style: ButtonStyle::SUCCESS,
            ),
        );

        // ─── 🔙 رجوع (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '🔙 رجوع',
                callback_data: 'admin.gift-codes',
            ),
        );

        return $keyboard;
    }

    // ============================================================
    //  📜 قائمة السجل
    // ============================================================
    public static function historyList(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔙 رجوع',
                    callback_data: 'admin.gift-codes',
                ),
            );
    }
}
