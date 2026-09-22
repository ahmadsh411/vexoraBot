<?php

namespace App\Telegram\Handlers\Admin\Communication;

use App\Telegram\Conversations\Admin\Communication\CommunicationConversation;
use App\Telegram\Keyboards\AdminsKeyboard\Communication\CommunicationKeyboard;
use App\Telegram\Screens\Admin\Communication\CommunicationScreen;
use SergiX44\Nutgram\Nutgram;

class CommunicationHandler
{
    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            CommunicationScreen::text(),
            CommunicationKeyboard::main(),
        );
    }

    public function broadcast(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        cache()->put(
            "comm.mode.{$bot->userId()}",
            'broadcast',
            now()->addMinutes(10),
        );

        CommunicationConversation::begin($bot);
    }

    public function single(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        cache()->put(
            "comm.mode.{$bot->userId()}",
            'single',
            now()->addMinutes(10),
        );

        CommunicationConversation::begin($bot);
    }

    public function cancel(Nutgram $bot): void
    {
        $this->safeAnswer($bot, 'تم الإلغاء');

        $this->safeEdit(
            $bot,
            CommunicationScreen::text(),
            CommunicationKeyboard::main(),
        );
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function safeAnswer(Nutgram $bot, string $text = ''): void
    {
        try {
            $bot->answerCallbackQuery(text: $text);
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
