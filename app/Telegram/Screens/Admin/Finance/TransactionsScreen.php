<?php

namespace App\Telegram\Screens\Admin\Finance;

use App\Models\Transaction;
use App\Telegram\Keyboards\AdminsKeyboard\Finance\TransactionsKeyboard;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class TransactionsScreen
{
    /**
     * نص القائمة الرئيسية (الفلاتر).
     */
    public static function filtersText(): string
    {
        $total     = Transaction::count();
        $pending   = Transaction::pending()->count();
        $completed = Transaction::completed()->count();
        $rejected  = Transaction::where('status', Transaction::STATUS_REJECTED)->count();

        return implode("\n", [
            '📜 <b>سجل العمليات</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الإحصائيات:</b>',
            '',
            '📋 الإجمالي: <b>' . $total . '</b>',
            '🟡 معلقة: <b>' . $pending . '</b>',
            '✅ مكتملة: <b>' . $completed . '</b>',
            '🔴 مرفوضة: <b>' . $rejected . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🔽 اختر الفلتر:',
        ]);
    }

    /**
     * كيبورد الفلاتر.
     */
    public static function filtersKeyboard(): InlineKeyboardMarkup
    {
        return TransactionsKeyboard::filtersKeyboard();
    }

    /**
     * نص قائمة العمليات.
     */
    public static function listText(string $filter, string $title): string
    {
        $transactions = self::getTransactions($filter);

        if ($transactions->isEmpty()) {
            return implode("\n", [
                $title,
                '━━━━━━━━━━━━━━━━━━',
                '',
                '❌ لا توجد عمليات.',
            ]);
        }

        $lines = [
            $title,
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 العدد: <b>' . $transactions->count() . '</b>',
            '',
        ];

        foreach ($transactions as $index => $transaction) {
            $number = $index + 1;
            $icon = TransactionsKeyboard::typeIcon($transaction->type);
            $statusIcon = TransactionsKeyboard::statusIcon($transaction->status);
            $amount = $transaction->isDeposit()
                ? $transaction->amount_to
                : $transaction->amount_from;
            $currency = $transaction->isDeposit()
                ? $transaction->to_currency
                : $transaction->from_currency;
            $username = $transaction->user?->username ?? 'غير معروف';
            $date = $transaction->created_at?->format('m-d H:i');

            $lines[] = "{$number}. {$statusIcon} {$icon} <b>#{$transaction->id}</b>";
            $lines[] = "   💰 " . number_format((float) $amount, 2) . " {$currency}";
            $lines[] = "   👤 {$username}";
            $lines[] = "   🕐 {$date}";
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * كيبورد قائمة العمليات.
     */
    public static function listKeyboard(string $filter): InlineKeyboardMarkup
    {
        return TransactionsKeyboard::transactionsKeyboard(
            self::getTransactions($filter),
            $filter,
        );
    }

    /**
     * نص تفاصيل عملية.
     */
    public static function detailsText(Transaction $transaction): string
    {
        $user = $transaction->user;
        $admin = $transaction->admin;

        $lines = [
            TransactionsKeyboard::typeIcon($transaction->type) . ' <b>تفاصيل العملية</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🆔 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
            '📊 <b>النوع:</b> ' . $transaction->typeLabel(),
            '🎯 <b>الحالة:</b> ' . $transaction->statusLabel(),
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👤 <b>المستخدم:</b>',
            '   الاسم: <b>' . ($user?->username ?? 'غير معروف') . '</b>',
            '   المعرف: <code>' . ($user?->id ?? '-') . '</code>',
            '   Telegram: <code>' . ($user?->telegram_id ?? '-') . '</code>',
            '',
        ];

        // المبالغ
        if ($transaction->amount_from > 0) {
            $lines[] = '💸 <b>من:</b> <b>'
                . number_format((float) $transaction->amount_from, 2)
                . ' ' . $transaction->from_currency . '</b>';
        }

        if ($transaction->amount_to > 0) {
            $lines[] = '💰 <b>إلى:</b> <b>'
                . number_format((float) $transaction->amount_to, 2)
                . ' ' . $transaction->to_currency . '</b>';
        }

        if ($transaction->exchange_rate > 0) {
            $lines[] = '💱 <b>سعر الصرف:</b> <code>'
                . $transaction->exchange_rate . '</code>';
        }

        if ($transaction->commission_amount > 0) {
            $lines[] = '💼 <b>عمولة:</b> <b>'
                . number_format((float) $transaction->commission_amount, 2)
                . '</b>';
        }

        $lines[] = '';

        // التواريخ
        $lines[] = '📅 <b>الإنشاء:</b> '
            . $transaction->created_at?->format('Y-m-d H:i');

        if ($transaction->approved_at) {
            $lines[] = '✅ <b>الاعتماد:</b> '
                . $transaction->approved_at->format('Y-m-d H:i');
        }

        if ($transaction->completed_at) {
            $lines[] = '🏁 <b>الإكمال:</b> '
                . $transaction->completed_at->format('Y-m-d H:i');
        }

        if ($transaction->rejected_at) {
            $lines[] = '🔴 <b>الرفض:</b> '
                . $transaction->rejected_at->format('Y-m-d H:i');
        }

        // الأدمن
        if ($admin) {
            $lines[] = '';
            $lines[] = '👮 <b>بواسطة:</b> ' . $admin->username;
        }

        // ملاحظات
        if ($transaction->notes) {
            $lines[] = '';
            $lines[] = '📝 <b>ملاحظات:</b>';
            $lines[] = htmlspecialchars($transaction->notes, ENT_QUOTES, 'UTF-8');
        }

        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━';

        return implode("\n", $lines);
    }

    /**
     * كيبورد التفاصيل.
     */
    public static function detailsKeyboard(Transaction $transaction, string $filter = 'all'): InlineKeyboardMarkup
    {
        return TransactionsKeyboard::detailsKeyboard($transaction, $filter);
    }

    // ============================================================
    //  Helpers
    // ============================================================

    public static function getTransactions(string $filter): \Illuminate\Support\Collection
    {
        $query = Transaction::latestFirst()->with(['user:id,username,telegram_id', 'admin:id,username']);

        return match ($filter) {
            'all'          => $query->limit(20)->get(),
            'deposits'     => $query->deposits()->limit(20)->get(),
            'withdrawals'  => $query->withdrawals()->limit(20)->get(),
            'exchanges'    => $query->ofType(Transaction::TYPE_EXCHANGE)->limit(20)->get(),
            'adjustments'  => $query->whereIn('type', [
                Transaction::TYPE_ADMIN_CREDIT,
                Transaction::TYPE_ADMIN_DEBIT,
            ])->limit(20)->get(),
            'pending'      => $query->pending()->limit(20)->get(),
            'completed'    => $query->completed()->limit(20)->get(),
            'rejected'     => $query->where('status', Transaction::STATUS_REJECTED)->limit(20)->get(),
            default        => $query->limit(20)->get(),
        };
    }
}
