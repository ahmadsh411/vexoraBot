<?php

namespace App\Telegram\Handlers\User;

use App\Telegram\Keyboards\AdminsKeyboard\Support\SupportKeyboard;
use App\Telegram\Screens\Admin\Support\SupportScreen;
use SergiX44\Nutgram\Nutgram;

class SupportHandler
{
    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            SupportScreen::user(),
            SupportKeyboard::user(),
        );
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function safeAnswer(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
            // تجاهل
        }
    }

    private function safeEdit(Nutgram $bot, string $text, $keyboard): void
    {
        try {
            $bot->editMessageText(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
                disable_web_page_preview: true,
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not modified')) {
                return;
            }

            try {
                $bot->sendMessage(
                    text: $text,
                    parse_mode: 'HTML',
                    reply_markup: $keyboard,
                    disable_web_page_preview: true,
                );
            } catch (\Throwable $e2) {
                // تجاهل
            }
        }
    }
}
