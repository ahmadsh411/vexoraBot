<?php

namespace App\Telegram\Handlers\Admin\Finance;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Telegram\Keyboards\AdminsKeyboard\Finance\MainWalletKeyboard;
use App\Telegram\Screens\Admin\Finance\MainWalletScreen;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class MainWalletHandler
{
    public function show(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            MainWalletScreen::text(),
            MainWalletKeyboard::make(),
        );
    }

    public function deposits(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $transactions = Transaction::completed()
            ->deposits()
            ->latestFirst()
            ->limit(10)
            ->with('user:id,username')
            ->get();

        $this->showTransactionsList($bot, $transactions, '📥 آخر الإيداعات', 'لا توجد إيداعات.');
    }

    public function withdraws(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $transactions = Transaction::completed()
            ->withdrawals()
            ->latestFirst()
            ->limit(10)
            ->with('user:id,username')
            ->get();

        $this->showTransactionsList($bot, $transactions, '📤 آخر السحوبات', 'لا توجد سحوبات.');
    }

    public function transactions(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $transactions = Transaction::completed()
            ->latestFirst()
            ->limit(20)
            ->with('user:id,username')
            ->get();

        $this->showTransactionsList($bot, $transactions, '📜 آخر 20 عملية', 'لا توجد عمليات.');
    }

    public function stats(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            MainWalletScreen::statsText(),
            InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '⬅️ رجوع',
                        callback_data: 'admin.finance.main-wallet',
                    ),
                ),
        );
    }

    public function sync(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery(
                text: '🔄 المزامنة ستُفعّل قريباً',
                show_alert: true,
            );
        } catch (\Throwable $e) {
        }
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function showTransactionsList(
        Nutgram $bot,
        $transactions,
        string $title,
        string $emptyMessage,
    ): void {
        if ($transactions->isEmpty()) {
            $this->safeEdit(
                $bot,
                implode("\n", [$title, '━━━━━━━━━━━━━━━━━━', '', "❌ {$emptyMessage}"]),
                InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '⬅️ رجوع',
                            callback_data: 'admin.finance.main-wallet',
                        ),
                    ),
            );
            return;
        }

        $lines = [$title, '━━━━━━━━━━━━━━━━━━', ''];

        foreach ($transactions as $index => $tx) {
            $number = $index + 1;
            $icon = $tx->isDeposit() ? '📥' : '📤';
            $amount = $tx->getEffectiveAmount();
            $currency = $tx->getEffectiveCurrency();

            $lines[] = "{$number}. {$icon} <b>" . number_format((float) $amount, 2) . " {$currency}</b>";
            $lines[] = "   👤 " . ($tx->user?->username ?? 'غير معروف');
            $lines[] = "   🕐 " . $tx->completed_at?->format('Y-m-d H:i');
            $lines[] = '';
        }

        $this->safeEdit(
            $bot,
            implode("\n", $lines),
            InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '⬅️ رجوع',
                        callback_data: 'admin.finance.main-wallet',
                    ),
                ),
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
