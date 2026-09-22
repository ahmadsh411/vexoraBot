<?php

namespace App\Telegram\Handlers\Admin\Finance;

use App\Telegram\Keyboards\AdminsKeyboard\Finance\ReportsKeyboard;
use App\Telegram\Screens\Admin\Finance\ReportsScreen;
use Carbon\Carbon;
use SergiX44\Nutgram\Nutgram;

class ReportsHandler
{
    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit($bot, ReportsScreen::text(), ReportsKeyboard::make());
    }

    public function today(Nutgram $bot): void
    {
        $this->showReport($bot, '📅 تقرير اليوم', now()->startOfDay(), now());
    }

    public function week(Nutgram $bot): void
    {
        $this->showReport($bot, '📆 تقرير آخر 7 أيام', now()->subDays(7)->startOfDay(), now());
    }

    public function month(Nutgram $bot): void
    {
        $this->showReport($bot, '🗓️ تقرير آخر 30 يوماً', now()->subDays(30)->startOfDay(), now());
    }

    public function thisMonth(Nutgram $bot): void
    {
        $this->showReport($bot, '📅 تقرير هذا الشهر', now()->startOfMonth(), now());
    }

    public function lastMonth(Nutgram $bot): void
    {
        $this->showReport(
            $bot,
            '📅 تقرير الشهر الماضي',
            now()->subMonth()->startOfMonth(),
            now()->subMonth()->endOfMonth(),
        );
    }

    public function year(Nutgram $bot): void
    {
        $this->showReport($bot, '📆 تقرير هذه السنة', now()->startOfYear(), now());
    }

    public function overview(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit(
            $bot,
            ReportsScreen::overviewText(),
            ReportsScreen::reportKeyboard(),
        );
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function showReport(Nutgram $bot, string $title, Carbon $from, Carbon $to): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            ReportsScreen::reportText($title, $from, $to),
            ReportsScreen::reportKeyboard(),
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
