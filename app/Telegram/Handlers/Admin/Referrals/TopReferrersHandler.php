<?php

namespace App\Telegram\Handlers\Admin\Referrals;

use App\Telegram\Keyboards\AdminsKeyboard\Referrals\TopReferrersKeyboard;
use App\Telegram\Screens\Admin\Referrals\TopReferrersScreen;
use SergiX44\Nutgram\Nutgram;

class TopReferrersHandler
{
    public function handle(Nutgram $bot, ?string $period = null): void
    {
        $this->safeAnswer($bot);

        if (! $period) {
            $period = $this->extractPeriod($bot) ?? 'all';
        }

        if (! in_array($period, ['all', 'month', 'week', 'today'], true)) {
            $period = 'all';
        }

        $this->safeEdit(
            $bot,
            TopReferrersScreen::text($period),
            TopReferrersKeyboard::make($period),
        );
    }

    private function extractPeriod(Nutgram $bot): ?string
    {
        $data = $bot->callbackQuery()?->data;

        if (! $data) {
            return null;
        }

        $parts = explode('.', $data);

        return $parts[3] ?? null;
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
