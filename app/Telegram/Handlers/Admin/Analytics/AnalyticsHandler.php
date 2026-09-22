<?php

namespace App\Telegram\Handlers\Admin\Analytics;

use App\Telegram\Keyboards\AdminsKeyboard\Analytics\AnalyticsKeyboard;
use App\Telegram\Screens\Admin\Analytics\AnalyticsScreen;
use SergiX44\Nutgram\Nutgram;

class AnalyticsHandler
{
    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit($bot, AnalyticsScreen::text(), AnalyticsKeyboard::make());
    }

    public function users(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit($bot, AnalyticsScreen::usersText(), AnalyticsKeyboard::users());
    }

    public function finance(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit($bot, AnalyticsScreen::financeText(), AnalyticsKeyboard::finance());
    }

    public function referrals(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit($bot, AnalyticsScreen::referralsText(), AnalyticsKeyboard::referrals());
    }

    public function comparison(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit(
            $bot,
            '📈 <b>المقارنات</b>' . "\n" . '━━━━━━━━━━━━━━━━━━' . "\n\n" . 'اختر الفترة:',
            AnalyticsKeyboard::comparison(),
        );
    }

    public function comparisonPeriod(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $data = $bot->callbackQuery()?->data ?? '';
        $parts = explode('.', $data);
        $period = end($parts);

        if (! in_array($period, ['day', 'week', 'month'], true)) {
            $period = 'day';
        }

        $this->safeEdit(
            $bot,
            AnalyticsScreen::comparisonText($period),
            AnalyticsKeyboard::comparison(),
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
