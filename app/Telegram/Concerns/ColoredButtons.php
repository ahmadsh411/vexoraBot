<?php

namespace App\Telegram\Concerns;

use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;

/**
 * 🎨 ColoredButtons — مساعد لإنشاء أزرار ملوّنة
 *
 * الاستخدام:
 *   use ColoredButtons;
 *
 *   $this->successButton('✅ تفعيل', 'toggle'),
 *   $this->dangerButton('❌ حذف', 'delete'),
 *   $this->primaryButton('👁️ عرض', 'show'),
 */
trait ColoredButtons
{
    /**
     * 🟢 زر أخضر (نجاح)
     */
    protected function successButton(
        string $text,
        string $callbackData,
    ): InlineKeyboardButton {
        return InlineKeyboardButton::make(
            text: $text,
            callback_data: $callbackData,
            style: ButtonStyle::SUCCESS,
        );
    }

    /**
     * 🔴 زر أحمر (خطر / حذف)
     */
    protected function dangerButton(
        string $text,
        string $callbackData,
    ): InlineKeyboardButton {
        return InlineKeyboardButton::make(
            text: $text,
            callback_data: $callbackData,
            style: ButtonStyle::DANGER,
        );
    }

    /**
     * 🔵 زر أزرق (رئيسي)
     */
    protected function primaryButton(
        string $text,
        string $callbackData,
    ): InlineKeyboardButton {
        return InlineKeyboardButton::make(
            text: $text,
            callback_data: $callbackData,
            style: ButtonStyle::PRIMARY,
        );
    }

    /**
     * ⚪ زر افتراضي (بدون لون)
     */
    protected function defaultButton(
        string $text,
        string $callbackData,
    ): InlineKeyboardButton {
        return InlineKeyboardButton::make(
            text: $text,
            callback_data: $callbackData,
        );
    }

    /**
     * 🎨 زر بلون مخصص (string أو enum)
     */
    protected function coloredButton(
        string $text,
        string $callbackData,
        ButtonStyle|string $style,
    ): InlineKeyboardButton {
        return InlineKeyboardButton::make(
            text: $text,
            callback_data: $callbackData,
            style: $style,
        );
    }
}
