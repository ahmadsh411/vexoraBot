<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Finance;

use App\Models\Transaction;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class FinanceKeyboard
{
    public static function make(): InlineKeyboardMarkup
    {
        $pendingDeposits = Transaction::pending()->deposits()->count();
        $pendingWithdraws = Transaction::pending()->withdrawals()->count();

        $depositLabel = $pendingDeposits > 0
            ? "📥 الإيداعات ({$pendingDeposits})"
            : '📥 الإيداعات';

        $withdrawLabel = $pendingWithdraws > 0
            ? "📤 السحوبات ({$pendingWithdraws})"
            : '📤 السحوبات';

        return InlineKeyboardMarkup::make()

            // ─── 🏦 المحفظة الرئيسية ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🏦 المحفظة الرئيسية',
                    callback_data: 'admin.finance.main-wallet',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── 📥 الإيداعات + 📤 السحوبات ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: $depositLabel,
                    callback_data: 'admin.finance.deposits',
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: $withdrawLabel,
                    callback_data: 'admin.finance.withdraws',
                    style: ButtonStyle::DANGER,
                ),
            )

            // ─── 💳 طرق الإيداع ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💳 طرق الإيداع',
                    callback_data: 'admin.finance.deposit-methods',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── 📊 التقارير + 📜 السجل ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📊 التقارير',
                    callback_data: 'admin.finance.reports',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '📜 سجل العمليات',
                    callback_data: 'admin.finance.transactions',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: 'admin.dashboard',
                ),
            );
    }
}
