<?php

namespace App\Telegram\Handlers\Admin\Referrals;

use App\Telegram\Keyboards\AdminsKeyboard\Referrals\AllReferralsKeyboard;
use App\Telegram\Screens\Admin\Referrals\AllReferralsScreen;
use SergiX44\Nutgram\Nutgram;

class AllReferralsHandler
{
    public function handle(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $page = $this->extractPage($bot) ?? 1;

        $this->safeEdit(
            $bot,
            AllReferralsScreen::text($page),
            AllReferralsKeyboard::make($page),
        );
    }

    public function noop(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
    }

    private function extractPage(Nutgram $bot): ?int
    {
        $data = $bot->callbackQuery()?->data;

        if (! $data) {
            return null;
        }

        preg_match('/\.page\.(\d+)/', $data, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
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
