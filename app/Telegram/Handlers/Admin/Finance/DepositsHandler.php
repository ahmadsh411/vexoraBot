<?php

namespace App\Telegram\Handlers\Admin\Finance;

use App\Models\Transaction;
use App\Telegram\Keyboards\AdminsKeyboard\Finance\DepositsKeyboard;
use App\Telegram\Screens\Admin\Finance\DepositsScreen;
use SergiX44\Nutgram\Nutgram;

class DepositsHandler
{
    // ============================================================
    //  📥 index
    // ============================================================

    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit($bot, DepositsScreen::text(), DepositsKeyboard::make());
    }

    public function approved(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit(
            $bot,
            DepositsScreen::listText('approved', '🟢 <b>طلبات الإيداع المعتمدة (اليوم)</b>'),
            DepositsScreen::listKeyboard('approved'),
        );
    }

    public function rejected(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit(
            $bot,
            DepositsScreen::listText('rejected', '🔴 <b>طلبات الإيداع المرفوضة (اليوم)</b>'),
            DepositsScreen::listKeyboard('rejected'),
        );
    }

    public function all(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit(
            $bot,
            DepositsScreen::listText('all', '📜 <b>كل طلبات الإيداع</b>'),
            DepositsScreen::listKeyboard('all'),
        );
    }

    // ============================================================
    //  👁️ show
    // ============================================================

    public function show(Nutgram $bot, ?string $id = null): void
    {
        $this->safeAnswer($bot);

        $id = $this->resolveId($id, $bot);

        if (! $id) {
            $this->safeAlert($bot, '❌ معرّف غير صالح.');
            return;
        }

        $transaction = Transaction::deposits()
            ->with('user:id,username,telegram_id')
            ->find($id);

        if (! $transaction) {
            $this->safeEdit($bot, '❌ الطلب غير موجود.', DepositsKeyboard::make());
            return;
        }

        $this->safeEdit(
            $bot,
            DepositsScreen::detailsText($transaction),
            DepositsScreen::detailsKeyboard($transaction),
        );
    }

    // ============================================================
    //  Helpers
    // ============================================================

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
