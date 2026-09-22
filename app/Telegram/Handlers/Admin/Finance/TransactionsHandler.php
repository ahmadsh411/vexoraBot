<?php

namespace App\Telegram\Handlers\Admin\Finance;

use App\Models\Transaction;
use App\Telegram\Keyboards\AdminsKeyboard\Finance\TransactionsKeyboard;
use App\Telegram\Screens\Admin\Finance\TransactionsScreen;
use SergiX44\Nutgram\Nutgram;

class TransactionsHandler
{
    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit(
            $bot,
            TransactionsScreen::filtersText(),
            TransactionsKeyboard::filtersKeyboard(),
        );
    }

    public function all(Nutgram $bot): void
    {
        $this->list($bot, 'all', '📜 <b>كل العمليات</b>');
    }

    public function deposits(Nutgram $bot): void
    {
        $this->list($bot, 'deposits', '📥 <b>عمليات الإيداع</b>');
    }

    public function withdrawals(Nutgram $bot): void
    {
        $this->list($bot, 'withdrawals', '📤 <b>عمليات السحب</b>');
    }

    public function exchanges(Nutgram $bot): void
    {
        $this->list($bot, 'exchanges', '💱 <b>عمليات التحويل</b>');
    }

    public function adjustments(Nutgram $bot): void
    {
        $this->list($bot, 'adjustments', '➕ <b>العمليات الإدارية</b>');
    }

    public function pending(Nutgram $bot): void
    {
        $this->list($bot, 'pending', '🟡 <b>العمليات المعلقة</b>');
    }

    public function completed(Nutgram $bot): void
    {
        $this->list($bot, 'completed', '✅ <b>العمليات المكتملة</b>');
    }

    public function rejected(Nutgram $bot): void
    {
        $this->list($bot, 'rejected', '🔴 <b>العمليات المرفوضة</b>');
    }

    public function show(Nutgram $bot, ?string $id = null): void
    {
        $this->safeAnswer($bot);

        $id = $this->resolveId($id, $bot);

        if (! $id) {
            $this->safeAlert($bot, '❌ معرّف غير صالح.');
            return;
        }

        $transaction = Transaction::with(['user', 'admin'])->find($id);

        if (! $transaction) {
            $this->safeEdit($bot, '❌ العملية غير موجودة.', TransactionsKeyboard::filtersKeyboard());
            return;
        }

        $this->safeEdit(
            $bot,
            TransactionsScreen::detailsText($transaction),
            TransactionsKeyboard::detailsKeyboard($transaction),
        );
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function list(Nutgram $bot, string $filter, string $title): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            TransactionsScreen::listText($filter, $title),
            TransactionsScreen::listKeyboard($filter),
        );
    }

    private function resolveId(?string $id, Nutgram $bot): ?int
    {
        if ($id !== null && is_numeric($id)) {
            return (int) $id;
        }

        $data = $bot->callbackQuery()?->data;

        if (! $data) {
            return null;
        }

        preg_match('/\.(\d+)(?:\.|$)/', $data, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }

    private function safeAnswer(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }
    }

    private function safeAlert(Nutgram $bot, string $text): void
    {
        try {
            $bot->answerCallbackQuery(text: $text, show_alert: true);
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
