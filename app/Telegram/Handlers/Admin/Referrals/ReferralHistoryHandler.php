<?php

namespace App\Telegram\Handlers\Admin\Referrals;

use App\Telegram\Keyboards\AdminsKeyboard\Referrals\ReferralHistoryKeyboard;
use App\Telegram\Screens\Admin\Referrals\ReferralHistoryScreen;
use SergiX44\Nutgram\Nutgram;

class ReferralHistoryHandler
{
    public function handle(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        [$filter, $page] = $this->extractParams($bot);

        $this->safeEdit(
            $bot,
            ReferralHistoryScreen::text($filter, $page),
            ReferralHistoryKeyboard::make($filter, $page),
        );
    }

    public function noop(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
    }

    private function extractParams(Nutgram $bot): array
    {
        $data = $bot->callbackQuery()?->data ?? '';

        $filter = 'all';
        $page = 1;

        $parts = explode('.', $data);

        if (isset($parts[3]) && $parts[3] !== 'page' && $parts[3] !== 'noop') {
            $filter = $parts[3];
        }

        if (preg_match('/\.page\.(\d+)/', $data, $matches)) {
            $page = (int) $matches[1];
        }

        if (! in_array($filter, ['all', 'instant', 'cycle', 'l1', 'l2'], true)) {
            $filter = 'all';
        }

        return [$filter, max(1, $page)];
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
