<?php

namespace App\Telegram\Keyboards\Base;

use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class BaseAdminKeyboard
{
    protected InlineKeyboardMarkup $keyboard;

    public static function build(): self
    {
        $instance = new self();
        $instance->keyboard = InlineKeyboardMarkup::make();
        return $instance;
    }

    public function fullButton(
        string $text,
        string $callback,
        ?ButtonStyle $style = null,
    ): self {
        $this->keyboard->addRow(
            InlineKeyboardButton::make(
                text: $text,
                callback_data: $callback,
                style: $style,
            ),
        );
        return $this;
    }

    public function pairButtons(
        string $text1,
        string $callback1,
        string $text2,
        string $callback2,
        ?ButtonStyle $style1 = null,
        ?ButtonStyle $style2 = null,
    ): self {
        $this->keyboard->addRow(
            InlineKeyboardButton::make(
                text: $text1,
                callback_data: $callback1,
                style: $style1,
            ),
            InlineKeyboardButton::make(
                text: $text2,
                callback_data: $callback2,
                style: $style2,
            ),
        );
        return $this;
    }

    public function backButton(string $callback = 'admin.dashboard'): self
    {
        $this->keyboard->addRow(
            InlineKeyboardButton::make(
                text: '⬅️ رجوع',
                callback_data: $callback,
            ),
        );
        return $this;
    }

    public function toMarkup(): InlineKeyboardMarkup
    {
        return $this->keyboard;
    }
}
