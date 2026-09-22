<?php

namespace App\Telegram\Handlers\Admin\Referrals;

use App\Telegram\Keyboards\AdminsKeyboard\Referrals\ReferralAdminKeyboard;
use App\Telegram\Screens\Admin\Referrals\ReferralAdminScreen;
use SergiX44\Nutgram\Nutgram;

class ReferralAdminHandler
{
    public function handle(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            ReferralAdminScreen::text(),
            ReferralAdminKeyboard::make(),
        );
    }

    private function safeAnswer(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }
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
