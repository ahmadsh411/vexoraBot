<?php

namespace App\Telegram\Screens\Admin\Users;

use App\Models\Transaction;
use App\Models\User;
use App\Telegram\Keyboards\AdminsKeyboard\Users\BalanceAdjustmentKeyboard;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class BalanceAdjustmentScreen
{
    /**
     * 🎨 Helper — تسمية العملة
     */
    private static function currencyLabel(string $code): string
    {
        return match (strtoupper($code)) {
            'NSP' => 'NSP (ليرة سورية جديدة)',
            'USD' => 'USD (دولار)',
            'NPS' => 'NPS (ليرة سورية قديمة)',
            'SYP' => 'SYP (ليرة سورية)',
            default => $code,
        };
    }

    /**
     * نص الشاشة الرئيسية.
     */
    public static function text(User $user): string
    {
        $wallet = $user->wallet;

        return implode("\n", [
            '✏️ <b>تعديل رصيد</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👤 <b>المستخدم:</b> ' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8'),
            '🆔 <b>المعرف:</b> <code>' . $user->id . '</code>',
            '',
            '💰 <b>الرصيد الحالي:</b>',
            '   💰 <b>' . number_format((float) ($wallet?->balance_nsp ?? 0), 2) . '</b> NSP (ليرة سورية جديدة)',
            '   💵 <b>' . number_format((float) ($wallet?->balance_usd ?? 0), 2) . '</b> USD (دولار)',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            'اختر الإجراء:',
        ]);
    }

    /**
     * كيبورد الشاشة الرئيسية.
     */
    public static function keyboard(User $user): InlineKeyboardMarkup
    {
        return BalanceAdjustmentKeyboard::make($user->id);
    }

    /**
     * نص سجل التعديلات.
     */
    public static function historyText(User $user): string
    {
        $transactions = Transaction::where('user_id', $user->id)
            ->whereIn('type', [
                Transaction::TYPE_ADMIN_CREDIT,
                Transaction::TYPE_ADMIN_DEBIT,
            ])
            ->latestFirst()
            ->limit(20)
            ->with('admin:id,username')
            ->get();

        if ($transactions->isEmpty()) {
            return implode("\n", [
                '📜 <b>سجل التعديلات</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👤 ' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8'),
                '',
                '❌ لا يوجد سجل تعديلات.',
            ]);
        }

        $lines = [
            '📜 <b>سجل التعديلات</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👤 ' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8'),
            '📊 العدد: <b>' . $transactions->count() . '</b>',
            '',
        ];

        foreach ($transactions as $index => $transaction) {
            $number = $index + 1;
            $isCredit = $transaction->type === Transaction::TYPE_ADMIN_CREDIT;
            $icon = $isCredit ? '➕' : '➖';
            $amount = $isCredit ? $transaction->amount_to : $transaction->amount_from;
            $currency = $isCredit ? $transaction->to_currency : $transaction->from_currency;
            $currencyLabel = self::currencyLabel($currency);

            $lines[] = "{$number}. {$icon} <b>" . number_format((float) $amount, 2)
                . " {$currencyLabel}</b>";

            if ($transaction->notes) {
                $lines[] = '   📝 ' . htmlspecialchars($transaction->notes, ENT_QUOTES, 'UTF-8');
            }

            $lines[] = '   👮 ' . ($transaction->admin?->username ?? 'غير معروف');
            $lines[] = '   🕐 ' . $transaction->created_at?->format('Y-m-d H:i');
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * كيبورد سجل التعديلات.
     */
    public static function historyKeyboard(User $user): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: "admin.users.balance.{$user->id}",
                ),
            );
    }
}
