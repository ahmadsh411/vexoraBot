<?php

namespace App\Telegram\Screens\Admin\Finance;

use App\Models\Transaction;
use App\Telegram\Keyboards\AdminsKeyboard\Finance\DepositsKeyboard;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class DepositsScreen
{
    /**
     * النص الرئيسي للقائمة.
     */
    public static function text(): string
    {
        $pendingCount = Transaction::pending()->deposits()->count();
        $pendingSum   = (float) Transaction::pending()->deposits()->sum('amount_to');

        $approvedToday = Transaction::completed()
            ->deposits()
            ->whereDate('completed_at', today())
            ->count();
        $approvedSum = (float) Transaction::completed()
            ->deposits()
            ->whereDate('completed_at', today())
            ->sum('amount_to');

        $rejectedToday = Transaction::where('status', Transaction::STATUS_REJECTED)
            ->deposits()
            ->whereDate('rejected_at', today())
            ->count();

        return implode("\n", [
            '📥 <b>طلبات الإيداع</b>',
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

    /**
     * الكيبورد الرئيسي.
     */
    public static function keyboard(): InlineKeyboardMarkup
    {
        return DepositsKeyboard::make();
    }

    /**
     * نص قائمة الطلبات.
     */
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
            $amount = (float) $transaction->amount_to;
            $currency = $transaction->to_currency;
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

    /**
     * كيبورد قائمة الطلبات.
     */
    public static function listKeyboard(string $status): InlineKeyboardMarkup
    {
        $transactions = self::getTransactions($status);

        $keyboard = \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup::make();

        foreach ($transactions->take(10) as $transaction) {
            $amount = (float) $transaction->amount_to;
            $currency = $transaction->to_currency;
            $username = $transaction->user?->username ?? 'غير معروف';

            $keyboard->addRow(
                \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                    text: "#{$transaction->id} | "
                        . number_format($amount, 0) . " {$currency} | {$username}",
                    callback_data: "admin.finance.deposits.show.{$transaction->id}",
                ),
            );
        }

        $keyboard->addRow(
            \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                text: '⬅️ رجوع',
                callback_data: 'admin.finance.deposits',
            ),
        );

        return $keyboard;
    }

    /**
     * نص تفاصيل طلب.
     */
    public static function detailsText(Transaction $transaction): string
    {
        $user = $transaction->user;

        return implode("\n", [
            '📥 <b>طلب إيداع</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🆔 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
            '📊 <b>الحالة:</b> ' . $transaction->statusLabel(),
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👤 <b>المستخدم:</b>',
            '   الاسم: <b>' . ($user?->username ?? 'غير معروف') . '</b>',
            '   المعرف: <code>' . ($user?->id ?? '-') . '</code>',
            '   Telegram: <code>' . ($user?->telegram_id ?? '-') . '</code>',
            '',
            '💰 <b>المبلغ:</b> <b>'
                . number_format((float) $transaction->amount_to, 2)
                . ' ' . $transaction->to_currency . '</b>',
            '',
            '📅 <b>تاريخ الإنشاء:</b> '
                . $transaction->created_at?->format('Y-m-d H:i'),
            '',
            '━━━━━━━━━━━━━━━━━━',
        ]);
    }

    /**
     * كيبورد التفاصيل.
     */
    public static function detailsKeyboard(Transaction $transaction): InlineKeyboardMarkup
    {
        return \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup::make()
            ->addRow(
                \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: 'admin.finance.deposits',
                ),
            );
    }

    /**
     * جلب الطلبات حسب الحالة.
     */
    private static function getTransactions(string $status): \Illuminate\Support\Collection
    {
        $query = Transaction::deposits()->latestFirst();

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
