<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Finance;

use App\Models\Transaction;
use App\Telegram\Keyboards\Base\BaseAdminKeyboard;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class TransactionsKeyboard extends BaseAdminKeyboard
{
    // ============================================================
    //  🔍 كيبورد الفلاتر
    // ============================================================
    public static function filtersKeyboard(): InlineKeyboardMarkup
    {
        return self::build()

            // ─── 📜 الكل ───
            ->fullButton(
                '📜 الكل',
                'admin.finance.transactions.all',
            )

            // ─── 📥 حسب النوع ───
            ->pairButtons(
                '📥 إيداعات',
                'admin.finance.transactions.deposits',
                '📤 سحوبات',
                'admin.finance.transactions.withdrawals',
            )

            // ─── 💱 حسب النوع (تابع) ───
            ->pairButtons(
                '💱 تحويلات',
                'admin.finance.transactions.exchanges',
                '⚙️ إدارية',
                'admin.finance.transactions.adjustments',
            )

            // ─── 🟡 حسب الحالة ───
            ->pairButtons(
                '🟡 معلقة',
                'admin.finance.transactions.pending',
                '✅ مكتملة',
                'admin.finance.transactions.completed',
            )

            // ─── 🔴 المرفوضة ───
            ->fullButton(
                '🔴 مرفوضة',
                'admin.finance.transactions.rejected',
            )

            // ─── ↩️ رجوع ───
            ->backButton('admin.finance')
            ->toMarkup();
    }

    // ============================================================
    //  📜 كيبورد قائمة العمليات
    // ============================================================
    public static function transactionsKeyboard(
        \Illuminate\Support\Collection $transactions,
        string $filter,
    ): InlineKeyboardMarkup {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($transactions->take(10) as $transaction) {
            $icon = self::typeIcon($transaction->type);
            $amount = $transaction->isDeposit()
                ? $transaction->amount_to
                : $transaction->amount_from;
            $currency = $transaction->isDeposit()
                ? $transaction->to_currency
                : $transaction->from_currency;
            $statusIcon = self::statusIcon($transaction->status);

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: "{$statusIcon} {$icon} #{$transaction->id} | "
                        . number_format((float) $amount, 0) . " {$currency}",
                    callback_data: "admin.finance.transactions.show.{$transaction->id}",
                    style: ButtonStyle::PRIMARY,
                ),
            );
        }

        // ─── 🔄 التحكم ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '🔄 تحديث',
                callback_data: "admin.finance.transactions.{$filter}",
                style: ButtonStyle::PRIMARY,
            ),
            InlineKeyboardButton::make(
                text: '↩️ رجوع',
                callback_data: 'admin.finance.transactions',
            ),
        );

        return $keyboard;
    }

    // ============================================================
    //  👁️ كيبورد تفاصيل عملية
    // ============================================================
    public static function detailsKeyboard(Transaction $transaction, string $filter = 'all'): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: "admin.finance.transactions.{$filter}",
                ),
            );
    }

    // ============================================================
    //  Helpers — أيقونات النوع
    // ============================================================
    public static function typeIcon(string $type): string
    {
        return match ($type) {
            Transaction::TYPE_DEPOSIT,
            Transaction::TYPE_DEPOSIT_USD      => '📥',

            Transaction::TYPE_WITHDRAW,
            Transaction::TYPE_WITHDRAW_USD     => '📤',

            Transaction::TYPE_EXCHANGE         => '💱',

            Transaction::TYPE_ICHANCY_DEPOSIT  => '🎮',
            Transaction::TYPE_ICHANCY_WITHDRAW => '🎮',

            Transaction::TYPE_COMMISSION       => '💼',
            Transaction::TYPE_REFUND           => '↩️',
            Transaction::TYPE_ADMIN_CREDIT     => '➕',
            Transaction::TYPE_ADMIN_DEBIT      => '➖',

            default                            => '❔',
        };
    }

    // ============================================================
    //  Helpers — أيقونات الحالة
    // ============================================================
    public static function statusIcon(string $status): string
    {
        return match ($status) {
            Transaction::STATUS_PENDING   => '🟡',
            Transaction::STATUS_APPROVED  => '🟢',
            Transaction::STATUS_COMPLETED => '✅',
            Transaction::STATUS_REJECTED  => '🔴',
            Transaction::STATUS_CANCELLED => '⚫',
            Transaction::STATUS_FAILED    => '❌',
            default                       => '❔',
        };
    }
}
