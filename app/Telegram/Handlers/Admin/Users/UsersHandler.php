<?php

namespace App\Telegram\Handlers\Admin\Users;

use App\Models\User;
use App\Telegram\Keyboards\AdminsKeyboard\Users\UsersKeyboard;
use App\Telegram\Screens\Admin\Users\UsersScreen;
use SergiX44\Nutgram\Nutgram;

class UsersHandler
{
    public function handle(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        $this->safeEdit(
            $bot,
            UsersScreen::text(),
            UsersKeyboard::make(),
        );
    }

    private function safeEdit(Nutgram $bot, string $text, $keyboard): void
    {
        try {
            $bot->editMessageText(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not modified')) {
                return;
            }

            $bot->sendMessage(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
            );
        }
    }
}
