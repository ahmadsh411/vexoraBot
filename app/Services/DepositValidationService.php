<?php

namespace App\Services;

use App\Models\DepositMethod;
use App\Models\Transaction;

class DepositValidationService
{
    /**
     * التحقق من طلب إيداع.
     *
     * @return array{valid: bool, error: ?string}
     */
    public function validate(DepositMethod $method, float $amount): array
    {
        // ✅ 1. المبلغ موجب
        if ($amount <= 0) {
            return [
                'valid' => false,
                'error' => '⚠️ المبلغ يجب أن يكون أكبر من صفر.',
            ];
        }

        // ✅ 2. الطريقة نشطة
        if (! $method->is_active) {
            return [
                'valid' => false,
                'error' => '⚠️ هذه الطريقة غير مفعّلة حاليًا.',
            ];
        }

        // ✅ 3. الحد الأدنى
        $minAmount = (float) $method->min_amount;

        if ($minAmount > 0 && $amount < $minAmount) {
            return [
                'valid' => false,
                'error' => implode("\n", [
                    '⚠️ <b>المبلغ أقل من الحد الأدنى</b>',
                    '',
                    '📉 <b>الحد الأدنى:</b>',
                    '└── <b>' . number_format($minAmount, 0) . '</b> ' . $method->currency,
                    '',
                    '📌 <b>المبلغ المُرسل:</b>',
                    '└── <b>' . number_format($amount, 0) . '</b> ' . $method->currency,
                ]),
            ];
        }

        // ✅ 4. الحد الأقصى
        $maxAmount = (float) $method->max_amount;

        if ($maxAmount > 0 && $amount > $maxAmount) {
            return [
                'valid' => false,
                'error' => implode("\n", [
                    '⚠️ <b>المبلغ أكبر من الحد الأقصى</b>',
                    '',
                    '📈 <b>الحد الأقصى:</b>',
                    '└── <b>' . number_format($maxAmount, 0) . '</b> ' . $method->currency,
                    '',
                    '📌 <b>المبلغ المُرسل:</b>',
                    '└── <b>' . number_format($amount, 0) . '</b> ' . $method->currency,
                ]),
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * التحقق من Transaction كامل.
     */
    public function validateTransaction(Transaction $transaction): array
    {
        $methodId = $transaction->metadata['method_id'] ?? null;

        if (! $methodId) {
            return ['valid' => true, 'error' => null];
        }

        $method = DepositMethod::find($methodId);

        if (! $method) {
            return [
                'valid' => false,
                'error' => '⚠️ طريقة الإيداع محذوفة من النظام.',
            ];
        }

        return $this->validate($method, (float) $transaction->amount_from);
    }

    /**
     * التحقق من تطابق العمولة.
     */
    public function validateCommission(
        Transaction $transaction,
        DepositMethod $method,
    ): array {
        $amount = (float) $transaction->amount_from;

        $expectedCommission = (float) $method->calculateCommission($amount);
        $actualCommission   = (float) $transaction->commission_amount;

        $difference = abs($expectedCommission - $actualCommission);

        if ($difference > 0.01) {
            return [
                'valid'    => false,
                'error'    => "العمولة المتوقعة: {$expectedCommission} | الفعلية: {$actualCommission}",
                'expected' => $expectedCommission,
                'actual'   => $actualCommission,
            ];
        }

        return [
            'valid'    => true,
            'error'    => null,
            'expected' => $expectedCommission,
            'actual'   => $actualCommission,
        ];
    }

    /**
     * تصحيح العمولة تلقائياً.
     */
    public function fixCommission(
        Transaction $transaction,
        DepositMethod $method,
    ): bool {
        $check = $this->validateCommission($transaction, $method);

        if ($check['valid']) {
            return true;
        }

        $expected   = $check['expected'];
        $amountFrom = (float) $transaction->amount_from;

        $transaction->update([
            'commission_amount' => $expected,
            'amount_to'         => $amountFrom - $expected,
        ]);

        return true;
    }
}
