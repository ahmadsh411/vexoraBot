<?php

namespace App\Telegram\Keyboards\User;

use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class UserTransactionsKeyboard
{
    // ============================================================
    //  🏠 القائمة الرئيسية
    // ============================================================
    public static function main(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()

            // ─── 📜 كل العمليات ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📜 كل العمليات',
                    callback_data: 'user.transactions.list.all.1',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── 📥 الإيداعات + 📤 السحوبات ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📥 الإيداعات',
                    callback_data: 'user.transactions.list.deposits.1',
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '📤 السحوبات',
                    callback_data: 'user.transactions.list.withdrawals.1',
                    style: ButtonStyle::PRIMARY,
                ),
            )

            // ─── 💱 التحويلات + 🟡 المعلقة ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💱 التحويلات',
                    callback_data: 'user.transactions.list.exchanges.1',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '🟡 المعلقة',
                    callback_data: 'user.transactions.list.pending.1',
                    style: ButtonStyle::DANGER,
                ),
            )

            // ─── ✅ المكتملة + 🔴 المرفوضة ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✅ المكتملة',
                    callback_data: 'user.transactions.list.completed.1',
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '🔴 المرفوضة',
                    callback_data: 'user.transactions.list.rejected.1',
                    style: ButtonStyle::DANGER,
                ),
            )

            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔙 رجوع',
                    callback_data: 'user.dashboard',
                ),
            );
    }

    // ============================================================
    //  📜 قائمة العمليات
    // ============================================================
    public static function list(
        string $filter,
        int $currentPage,
        int $totalPages,
        $transactions = [],
    ): InlineKeyboardMarkup {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($transactions as $tx) {
            $statusIcon = \App\Telegram\Screens\User\UserTransactionsScreen::statusIcon($tx->status);
            $typeIcon = \App\Telegram\Screens\User\UserTransactionsScreen::typeIcon($tx->type);

            $amount = $tx->getEffectiveAmount();
            $currency = $tx->getEffectiveCurrency();

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: $statusIcon . ' ' . $typeIcon
                        . ' #' . $tx->id
                        . ' | ' . number_format((float) $amount, 0) . ' ' . $currency,
                    callback_data: 'user.transactions.show.' . $tx->id,
                    style: ButtonStyle::PRIMARY,
                ),
            );
        }

        // ─── Navigation ───
        $navRow = [];

        if ($currentPage > 1) {
            $navRow[] = InlineKeyboardButton::make(
                text: '◀️ السابق',
                callback_data: 'user.transactions.list.' . $filter . '.' . ($currentPage - 1),
                style: ButtonStyle::PRIMARY,
            );
        }

        $navRow[] = InlineKeyboardButton::make(
            text: '📄 ' . $currentPage . '/' . $totalPages,
            callback_data: 'user.transactions.noop',
        );

        if ($currentPage < $totalPages) {
            $navRow[] = InlineKeyboardButton::make(
                text: 'التالي ▶️',
                callback_data: 'user.transactions.list.' . $filter . '.' . ($currentPage + 1),
                style: ButtonStyle::PRIMARY,
            );
        }

        if (! empty($navRow)) {
            $keyboard->addRow(...$navRow);
        }

        // ─── ↩️ رجوع (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '🔙 رجوع',
                callback_data: 'user.transactions',
            ),
        );

        return $keyboard;
    }

    // ============================================================
    //  👁️ تفاصيل عملية
    // ============================================================
    public static function details(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔙 رجوع للسجل',
                    callback_data: 'user.transactions',
                ),
            );
    }
}
