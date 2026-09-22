<?php

namespace App\Telegram\Screens\Admin\Finance;

use App\Models\Transaction;
use App\Telegram\Keyboards\AdminsKeyboard\Finance\WithdrawsKeyboard;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class WithdrawsScreen
{
    public static function text(): string
    {
        $pendingCount = Transaction::pending()->withdrawals()->count();
        $pendingSum   = (float) Transaction::pending()->withdrawals()->sum('amount_from');

        $approvedToday = Transaction::completed()
            ->withdrawals()
            ->whereDate('completed_at', today())
            ->count();
        $approvedSum = (float) Transaction::completed()
            ->withdrawals()
            ->whereDate('completed_at', today())
            ->sum('amount_from');

        $rejectedToday = Transaction::where('status', Transaction::STATUS_REJECTED)
            ->withdrawals()
            ->whereDate('rejected_at', today())
            ->count();

        return implode("\n", [
            '📤 <b>طلبات السحب</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الإحصائيات:</b>',
            '',
            '🟡 <b>معلقة:</b>',
            '   العدد: <b>' . $pendingCount . '</b>',
            '   المجموع: <b>' . number_format($pendingSum, 2) . '</b>',
            '',
            '🟢 <b>معتمدة اليوم:</b>',
            '   العدد: <b>' . $approvedToday . '</b>',
            '   المجموع: <b>' . number_format($approvedSum, 2) . '</b>',
            '',
            '🔴 <b>مرفوضة اليوم:</b> <b>' . $rejectedToday . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
        ]);
    }

    public static function keyboard(): InlineKeyboardMarkup
    {
        return WithdrawsKeyboard::make();
    }

    public static function listText(string $status, string $title): string
    {
        $transactions = self::getTransactions($status);

        if ($transactions->isEmpty()) {
            return implode("\n", [
                $title,
                '━━━━━━━━━━━━━━━━━━',
                '',
                '❌ لا توجد طلبات.',
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
            $amount = (float) $transaction->amount_from;
            $currency = $transaction->from_currency;
            $username = $transaction->user?->username ?? 'غير معروف';
            $date = $transaction->created_at?->format('m-d H:i');

            $lines[] = "{$number}. <b>#{$transaction->id}</b> | <b>"
                . number_format($amount, 2) . " {$currency}</b>";
            $lines[] = "   👤 {$username}";
            $lines[] = "   🕐 {$date}";
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    public static function listKeyboard(string $status): InlineKeyboardMarkup
    {
        $transactions = self::getTransactions($status);

        $keyboard = InlineKeyboardMarkup::make();

        foreach ($transactions->take(10) as $transaction) {
            $amount = (float) $transaction->amount_from;
            $currency = $transaction->from_currency;
            $username = $transaction->user?->username ?? 'غير معروف';

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: "#{$transaction->id} | "
                        . number_format($amount, 0) . " {$currency} | {$username}",
                    callback_data: "admin.finance.withdraws.show.{$transaction->id}",
                ),
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '⬅️ رجوع',
                callback_data: 'admin.finance.withdraws',
            ),
        );

        return $keyboard;
    }

    public static function detailsText(Transaction $transaction): string
    {
        $user = $transaction->user;
        $meta = $transaction->metadata ?? [];

        $feePercent = $meta['fee_percent'] ?? 0;
        $feeAmount  = $meta['fee_amount'] ?? 0;
        $total      = $meta['total_deducted'] ?? 0;
        $destination = $meta['destination'] ?? $transaction->user_account_number;
        $destName    = $meta['destination_name'] ?? null;
        $methodName  = $meta['method_name'] ?? '—';
        $methodIcon  = $meta['method_icon'] ?? '💰';

        $lines = [
            '📤 <b>تفاصيل طلب السحب</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🆔 <b>العملية:</b> <code>#' . $transaction->id . '</code>',
            '🔖 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
            '📊 <b>الحالة:</b> ' . $transaction->statusLabel(),
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👤 <b>المستخدم:</b>',
            '   الاسم: <b>' . ($user?->username ?? 'غير معروف') . '</b>',
            '   المعرف: <code>#' . ($user?->id ?? '-') . '</code>',
            '   Telegram: <code>' . ($user?->telegram_id ?? '-') . '</code>',
            '',
            '💳 <b>الطريقة:</b> ' . $methodIcon . ' ' . $methodName,
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>المبلغ:</b> <b>' . number_format((float) $transaction->amount_from, 2) . '</b> ' . $transaction->from_currency,
            '💼 <b>العمولة (' . $feePercent . '%):</b> <b>' . number_format((float) $feeAmount, 2) . '</b>',
            '💳 <b>الإجمالي المخصوم:</b> <b>' . number_format((float) $total, 2) . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎯 <b>الاستلام:</b>',
            '└── <code>' . htmlspecialchars((string) $destination, ENT_QUOTES, 'UTF-8') . '</code>',
        ];

        if ($destName) {
            $lines[] = '└── 👤 ' . htmlspecialchars($destName, ENT_QUOTES, 'UTF-8');
        }

        $lines[] = '';
        $lines[] = '📅 <b>الإنشاء:</b> ' . $transaction->created_at?->format('Y-m-d H:i');

        if ($transaction->approved_at) {
            $lines[] = '✅ <b>الاعتماد:</b> ' . $transaction->approved_at->format('Y-m-d H:i');
        }

        if ($transaction->completed_at) {
            $lines[] = '🏁 <b>الإكمال:</b> ' . $transaction->completed_at->format('Y-m-d H:i');
        }

        if ($transaction->rejected_at) {
            $lines[] = '🔴 <b>الرفض:</b> ' . $transaction->rejected_at->format('Y-m-d H:i');
        }

        // ✅ سبب الرفض
        if (! empty($meta['reject_reason'])) {
            $lines[] = '';
            $lines[] = '📝 <b>سبب الرفض:</b>';
            $lines[] = '<i>' . htmlspecialchars($meta['reject_reason'], ENT_QUOTES, 'UTF-8') . '</i>';
        }

        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━';

        return implode("\n", $lines);
    }

    public static function detailsKeyboard(Transaction $transaction): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        if ($transaction->isPending()) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '✅ موافقة',
                    callback_data: "admin.finance.withdraws.approve.{$transaction->id}",
                ),
                InlineKeyboardButton::make(
                    text: '❌ رفض',
                    callback_data: "admin.finance.withdraws.reject.{$transaction->id}",
                ),
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '⬅️ رجوع',
                callback_data: 'admin.finance.withdraws',
            ),
        );

        return $keyboard;
    }

    private static function getTransactions(string $status): \Illuminate\Support\Collection
    {
        $query = Transaction::withdrawals()->latestFirst();

        return match ($status) {
            'pending'  => $query->pending()->limit(20)->get(),
            'approved' => $query->completed()->whereDate('completed_at', today())->limit(20)->get(),
            'rejected' => $query->where('status', Transaction::STATUS_REJECTED)
                ->whereDate('rejected_at', today())
                ->limit(20)
                ->get(),
            'all'      => $query->limit(20)->get(),
            default    => collect(),
        };
    }
}
