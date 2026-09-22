<?php

namespace App\Services;

use App\Models\DepositMethod;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;

class WithdrawValidationService
{
    public function __construct(
        private readonly WithdrawFeeService $feeService,
    ) {}

    /**
     * التحقق الكامل من طلب سحب.
     *
     * @return array{valid: bool, error: ?string, fee: float, total: float, percent: float}
     */
    public function validate(User $user, DepositMethod $method, float $amount): array
    {
        // ✅ 1. السحب مفعّل
        if (! (bool) Setting::get('withdraws_enabled', true)) {
            return $this->fail('⚠️ خدمة السحب معطّلة مؤقتاً.');
        }

        // ✅ 2. المبلغ موجب
        if ($amount <= 0) {
            return $this->fail('⚠️ المبلغ يجب أن يكون أكبر من صفر.');
        }

        // ✅ 3. الطريقة نشطة
        if (! $method->is_active) {
            return $this->fail('⚠️ هذه الطريقة غير مفعّلة حالياً.');
        }

        // ✅ 4. الحدود
        $min = (float) $method->min_amount;
        $max = (float) $method->max_amount;

        if ($min > 0 && $amount < $min) {
            return $this->fail(
                "⚠️ <b>المبلغ أقل من الحد الأدنى</b>\n\n"
                    . "📉 الحد الأدنى: <b>" . number_format($min, 0) . "</b> {$method->currency}"
            );
        }

        if ($max > 0 && $amount > $max) {
            return $this->fail(
                "⚠️ <b>المبلغ أكبر من الحد الأقصى</b>\n\n"
                    . "📈 الحد الأقصى: <b>" . number_format($max, 0) . "</b> {$method->currency}"
            );
        }

        // ✅ 5. حساب العمولة
        $feeData = $this->feeService->calculate($amount);
        $fee     = $feeData['fee'];
        $total   = $amount + $fee;
        $percent = $feeData['percent'];

        // ✅ 6. الحساب نشط
        if (! $user->is_active) {
            return $this->fail('⚠️ حسابك غير نشط.', $fee, $total, $percent);
        }

        // ✅ 7. الرصيد كافٍ (double-check قبل القفل)
        $wallet = $user->wallet;

        if (! $wallet) {
            return $this->fail('⚠️ لا توجد محفظة.', $fee, $total, $percent);
        }

        $balance = $wallet->getBalance($method->currency);

        if ($balance < $total) {
            return $this->fail(
                implode("\n", [
                    '⚠️ <b>رصيدك غير كافٍ</b>',
                    '',
                    '💰 <b>المطلوب:</b>',
                    '├── المبلغ: <b>' . number_format($amount, 2) . '</b>',
                    '└── العمولة: <b>' . number_format($fee, 2) . '</b>',
                    '     الإجمالي: <b>' . number_format($total, 2) . '</b> ' . $method->currency,
                    '',
                    '💼 <b>رصيدك:</b> <b>' . number_format($balance, 2) . '</b> ' . $method->currency,
                ]),
                $fee,
                $total,
                $percent
            );
        }

        // ✅ 8. لا يوجد طلب سحب معلّق
        $pendingCount = Transaction::where('user_id', $user->id)
            ->withdrawals()
            ->pending()
            ->count();

        if ($pendingCount > 0) {
            return $this->fail(
                '⚠️ لديك طلب سحب معلّق بالفعل. انتظر الموافقة أو الرفض.',
                $fee,
                $total,
                $percent
            );
        }

        return [
            'valid'   => true,
            'error'   => null,
            'fee'     => $fee,
            'total'   => $total,
            'percent' => $percent,
        ];
    }

    /**
     * استجابة فشل موحّدة.
     */
    private function fail(
        string $error,
        float $fee = 0,
        float $total = 0,
        float $percent = 0,
    ): array {
        return [
            'valid'   => false,
            'error'   => $error,
            'fee'     => $fee,
            'total'   => $total,
            'percent' => $percent,
        ];
    }
}
