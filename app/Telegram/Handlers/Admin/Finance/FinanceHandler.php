<?php

namespace App\Telegram\Handlers\Admin\Finance;

use App\Telegram\Keyboards\AdminsKeyboard\Finance\FinanceKeyboard;
use App\Telegram\Screens\Admin\Finance\FinanceScreen;
use SergiX44\Nutgram\Nutgram;

class FinanceHandler
{
    public function handle(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        $this->safeEdit(
            $bot,
            FinanceScreen::text(),
            FinanceKeyboard::make(),
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
