<?php

namespace App\Telegram\Screens\User;

use App\Models\DepositMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WithdrawFeeService;

class UserWithdrawScreen
{
    // ============================================================
    //  💳 اختيار الطريقة
    // ============================================================

    public static function chooseMethod(User $user): string
    {
        $wallet = $user->wallet;

        $balanceSyp = $wallet ? number_format((float) $wallet->balance_nsp, 0) : '0';
        $balanceUsd = $wallet ? number_format((float) $wallet->balance_usd, 2) : '0.00';

        $feePercent = app(WithdrawFeeService::class)->getPercent();

        return implode("\n", [
            '📤 <b>السحب من المحفظة</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💰 <b>رصيدك:</b>',
            '├── 💰 NSP (ليرة سورية جديدة): <b>' . $balanceSyp . '</b>',
            '└── 💵 USD (دولار): <b>' . $balanceUsd . '</b>',
            '',
            '💼 <b>عمولة السحب:</b> <b>' . $feePercent . '%</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💳 اختر <b>طريقة السحب</b>:',
        ]);
    }

    // ============================================================
    //  💼 اختيار الحساب
    // ============================================================

    public static function chooseAccount(DepositMethod $method, $accounts): string
    {
        $lines = [
            $method->icon . ' <b>' . htmlspecialchars($method->name, ENT_QUOTES, 'UTF-8') . '</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💼 <b>اختر حساب الاستلام:</b>',
            '',
        ];

        foreach ($accounts as $index => $account) {
            $number = $index + 1;
            $name = $account->account_name
                ? ' — ' . htmlspecialchars($account->account_name, ENT_QUOTES, 'UTF-8')
                : '';

            $lines[] = "{$number}. <code>"
                . htmlspecialchars($account->account_number, ENT_QUOTES, 'UTF-8')
                . "</code>{$name}";
        }

        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━';
        $lines[] = '';
        $lines[] = '💡 أو أضف حساباً جديداً.';

        return implode("\n", $lines);
    }

    // ============================================================
    //  📝 طلب الوجهة
    // ============================================================

    public static function askDestination(DepositMethod $method): string
    {
        if ($method->isSyriatel()) {
            return implode("\n", [
                '📱 <b>رقم GSM للاستلام</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📝 أرسل رقم GSM الذي تريد استلام المبلغ عليه:',
                '',
                '💡 يجب أن يبدأ بـ <code>09</code> ويتكون من 10 أرقام',
                'مثال: <code>0933000000</code>',
                '',
                '↩️ أو /cancel للإلغاء.',
            ]);
        }

        return implode("\n", [
            '🏦 <b>عنوان محفظة شام كاش</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📝 أرسل عنوان محفظتك في شام كاش:',
            '',
            '💡 32 حرف hex',
            'مثال: <code>06ff99d12f3b34d7956e3caaf756873e</code>',
            '',
            '↩️ أو /cancel للإلغاء.',
        ]);
    }

    // ============================================================
    //  👤 اسم صاحب الحساب
    // ============================================================

    public static function askDestinationName(): string
    {
        return implode("\n", [
            '👤 <b>اسم صاحب الحساب</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📝 أرسل اسم صاحب الحساب (اختياري):',
            '',
            '💡 يساعد الإدارة في المراجعة',
            '',
            '↩️ أرسل <code>/skip</code> للتخطي',
        ]);
    }

    // ============================================================
    //  💵 طلب المبلغ
    // ============================================================

    public static function askAmount(User $user, DepositMethod $method): string
    {
        $feePercent = app(WithdrawFeeService::class)->getPercent();

        $wallet = $user->wallet;
        $balance = $method->currency === 'USD'
            ? (float) ($wallet?->balance_usd ?? 0)
            : (float) ($wallet?->balance_nsp ?? 0);

        $currencyIcon = $method->currency === 'USD' ? '💵' : '🇸🇾';
        $currencyLabel = self::currencyLabel($method->currency);

        $lines = [
            $method->icon . ' <b>' . htmlspecialchars($method->name, ENT_QUOTES, 'UTF-8') . '</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            $currencyIcon . ' <b>العملة:</b> ' . $currencyLabel,
            '💰 <b>رصيدك:</b> <b>' . number_format($balance, 2) . '</b> ' . $currencyLabel,
            '',
            '📊 <b>الحدود:</b>',
        ];

        if ($method->min_amount > 0) {
            $lines[] = '├── 📉 <b>الأدنى:</b> <b>' . number_format((float) $method->min_amount, 0) . '</b> ' . $currencyLabel;
        } else {
            $lines[] = '├── 📉 <b>الأدنى:</b> لا يوجد';
        }

        if ($method->max_amount > 0) {
            $lines[] = '└── 📈 <b>الأقصى:</b> <b>' . number_format((float) $method->max_amount, 0) . '</b> ' . $currencyLabel;
        } else {
            $lines[] = '└── 📈 <b>الأقصى:</b> لا يوجد';
        }

        $lines[] = '';
        $lines[] = '💼 <b>العمولة:</b> <b>' . $feePercent . '%</b>';
        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━';
        $lines[] = '';
        $lines[] = '📝 أرسل <b>المبلغ</b>:';
        $lines[] = '';
        $lines[] = '↩️ أو /cancel للإلغاء.';

        return implode("\n", $lines);
    }

    // ============================================================
    //  ✅ التأكيد
    // ============================================================

    public static function confirm(
        User $user,
        DepositMethod $method,
        float $amount,
        float $fee,
        float $total,
        float $percent,
        string $destination,
        ?string $destinationName,
    ): string {
        $wallet = $user->wallet;
        $balanceAfter = $method->currency === 'USD'
            ? (float) ($wallet?->balance_usd ?? 0) - $total
            : (float) ($wallet?->balance_nsp ?? 0) - $total;

        $currencyLabel = self::currencyLabel($method->currency);

        $lines = [
            '✅ <b>تأكيد طلب السحب</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💳 <b>الطريقة:</b> ' . $method->full_name,
            '💰 <b>العملة:</b> ' . $currencyLabel,
            '',
            '🎯 <b>الاستلام:</b>',
            '└── <code>' . htmlspecialchars($destination, ENT_QUOTES, 'UTF-8') . '</code>',
        ];

        if ($destinationName) {
            $lines[] = '└── 👤 ' . htmlspecialchars($destinationName, ENT_QUOTES, 'UTF-8');
        }

        $lines = array_merge($lines, [
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>المبلغ:</b> <b>' . number_format($amount, 2) . '</b> ' . $currencyLabel,
            '💼 <b>العمولة (' . $percent . '%):</b> <b>' . number_format($fee, 2) . '</b> ' . $currencyLabel,
            '━━━━━━━━━━━━━━━━━━',
            '💳 <b>الإجمالي المخصوم:</b> <b>' . number_format($total, 2) . '</b> ' . $currencyLabel,
            '',
            '💰 <b>الرصيد بعد السحب:</b> <b>' . number_format($balanceAfter, 2) . '</b> ' . $currencyLabel,
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '⚠️ <b>هل أنت متأكد؟</b>',
        ]);

        return implode("\n", $lines);
    }

    // ============================================================
    //  ⏳ النجاح
    // ============================================================

    public static function success(Transaction $transaction, DepositMethod $method): string
    {
        $meta = $transaction->metadata ?? [];
        $feePercent = $meta['fee_percent'] ?? 0;
        $feeAmount  = $meta['fee_amount'] ?? 0;
        $total      = $meta['total_deducted'] ?? 0;

        $currencyLabel = self::currencyLabel($method->currency);

        return implode("\n", [
            '⏳ <b>تم إنشاء طلب السحب</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🆔 <b>رقم العملية:</b>',
            '└── <code>#' . $transaction->id . '</code>',
            '',
            '🔖 <b>المرجع:</b>',
            '└── <code>' . $transaction->reference . '</code>',
            '',
            '💳 <b>الطريقة:</b> ' . $method->full_name,
            '',
            '📊 <b>المبلغ:</b> <b>' . number_format((float) $transaction->amount_from, 2) . '</b> ' . $currencyLabel,
            '💼 <b>العمولة (' . $feePercent . '%):</b> <b>' . number_format((float) $feeAmount, 2) . '</b> ' . $currencyLabel,
            '💳 <b>الإجمالي المخصوم:</b> <b>' . number_format((float) $total, 2) . '</b> ' . $currencyLabel,
            '',
            '🎯 <b>الاستلام:</b>',
            '└── <code>' . htmlspecialchars((string) $transaction->user_account_number, ENT_QUOTES, 'UTF-8') . '</code>',
            '',
            '📊 <b>الحالة:</b> 🟡 بانتظار الموافقة',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '⏱ سيتم المراجعة خلال <b>5 - 30 دقيقة</b>.',
            '',
            '🔔 سيصلك إشعار عند الموافقة.',
        ]);
    }

    // ============================================================
    //  🎨 Helper — تسمية العملة
    // ============================================================

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
}
