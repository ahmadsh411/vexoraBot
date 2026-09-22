<?php

namespace App\Services;

use App\Models\DepositMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserPaymentAccount;
use App\Models\Wallet;
use App\Services\Concerns\HasLockingHelpers;
use App\Services\PaymentGateway\PaymentGatewayService;
use App\Helpers\ErrorMessages;

class WithdrawService
{
    use HasLockingHelpers;

    public function __construct(
        private readonly PaymentGatewayService $gateway,
        private readonly WithdrawValidationService $validator,
        private readonly UserPaymentAccountService $paymentAccountService,
    ) {}

    // ============================================================
    //  إنشاء طلب سحب (مع حجز فوري)
    // ============================================================

    public function createRequest(
        User $user,
        DepositMethod $method,
        float $amount,
        string $destination,
        ?string $destinationName = null,
    ): array {
        $validation = $this->validator->validate($user, $method, $amount);

        if (! $validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        $fee     = $validation['fee'];
        $total   = $validation['total'];
        $percent = $validation['percent'];

        try {
            return $this->runInTransaction(function () use (
                $user,
                $method,
                $amount,
                $destination,
                $destinationName,
                $fee,
                $total,
                $percent
            ) {
                // ✅ 1. قفل المحفظة
                $wallet = Wallet::query()
                    ->where('user_id', $user->id)
                    ->where('type', Wallet::TYPE_USER)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $wallet->canTransact()) {
                    throw new \RuntimeException('المحفظة مجمّدة');
                }

                // ✅ 2. تحقق من الرصيد مرة أخرى (double-check)
                if (! $wallet->hasEnough($method->currency, $total)) {
                    throw new \RuntimeException('رصيد غير كافٍ');
                }

                // ✅ 3. خصم فوري (حجز)
                $wallet->debit($method->currency, $total);

                // ✅ 4. حساب/إنشاء حساب الدفع
                $paymentAccount = $this->paymentAccountService->findOrCreate(
                    user: $user,
                    method: $method,
                    accountNumber: $destination,
                    accountName: $destinationName,
                );

                // ✅ 5. إنشاء Transaction
                $transaction = Transaction::create([
                    'user_id'                 => $user->id,
                    'from_wallet_id'          => $wallet->id,
                    'user_payment_account_id' => $paymentAccount->id,
                    'user_account_number'     => $destination,
                    'type'                    => $method->currency === 'USD'
                        ? Transaction::TYPE_WITHDRAW_USD
                        : Transaction::TYPE_WITHDRAW,
                    'from_currency'           => $method->currency,
                    'to_currency'             => $method->currency,
                    'amount_from'             => $amount,
                    'amount_to'               => $amount,
                    'commission_amount'       => $fee,
                    'status'                  => Transaction::STATUS_PENDING,
                    'notes'                   => 'سحب عبر ' . $method->name,
                    'metadata'                => [
                        'method_id'        => $method->id,
                        'method_code'      => $method->code,
                        'method_name'      => $method->name,
                        'method_icon'      => $method->icon,
                        'method_currency'  => $method->currency,
                        'destination'      => $destination,
                        'destination_name' => $destinationName,
                        'fee_percent'      => $percent,
                        'fee_amount'       => $fee,
                        'total_deducted'   => $total,
                        'source_gsm'       => $method->receiver_gsm,
                        'source_address'   => $method->receiver_address,
                    ],
                ]);

                $this->logFinancialSuccess('withdraw.created', [
                    'transaction_id' => $transaction->id,
                    'user_id'        => $user->id,
                    'amount'         => $amount,
                    'fee'            => $fee,
                    'total'          => $total,
                    'method'         => $method->code,
                ]);

                return ['success' => true, 'transaction' => $transaction];
            });
        } catch (\Throwable $e) {
            $this->logFinancialError('withdraw.create', [
                'user_id' => $user->id,
                'amount'  => $amount,
            ], $e);

            return ['success' => false, 'error' => '❌ فشل الإنشاء: ' . $e->getMessage()];
        }
    }

    // ============================================================
    //  الموافقة (مع إرسال تلقائي)
    // ============================================================

    public function approve(Transaction $transaction, User $admin): array
    {
        try {
            return $this->runInTransaction(function () use ($transaction, $admin) {
                // ✅ قفل العملية
                $locked = Transaction::query()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($locked->status, [
                    Transaction::STATUS_PENDING,
                    Transaction::STATUS_APPROVED,
                ], true)) {
                    return ['success' => false, 'error' => 'الطلب تمت معالجته.'];
                }

                // ✅ جلب الطريقة
                $methodId = $locked->metadata['method_id'] ?? null;
                $method = $methodId ? DepositMethod::find($methodId) : null;

                if (! $method) {
                    return ['success' => false, 'error' => 'الطريقة غير موجودة.'];
                }

                $destination = $locked->metadata['destination'] ?? null;

                if (! $destination) {
                    return ['success' => false, 'error' => 'لا يوجد عنوان استلام.'];
                }

                // ✅ الإرسال (خارج الـ transaction للـ API)
                // نستخدم closure منفصلة لتجنب مشاكل الـ HTTP داخل قفل DB
                $sendResult = $this->sendViaGateway($method, $locked, $destination);

                if (! $sendResult['success']) {
                    // ❌ فشل → استرداد + رفض
                    $this->refund($locked, $sendResult['error'] ?? 'فشل الإرسال');

                    $locked->update([
                        'status'      => Transaction::STATUS_FAILED,
                        'admin_id'    => $admin->id,
                        'rejected_at' => now(),
                        'failed_at'   => now(),
                        'notes'       => 'فشل الإرسال: ' . ($sendResult['error'] ?? 'خطأ غير معروف'),
                    ]);

                    return [
                        'success' => false,
                        'error'   => '❌ فشل الإرسال: ' . ($sendResult['error'] ?? 'خطأ'),
                    ];
                }

                // ✅ نجاح
                $locked->update([
                    'status'       => Transaction::STATUS_COMPLETED,
                    'admin_id'     => $admin->id,
                    'approved_at'  => now(),
                    'completed_at' => now(),
                    'metadata'     => array_merge($locked->metadata ?? [], [
                        'gateway_response' => $sendResult['raw'] ?? null,
                        'sent_at'          => now()->toDateTimeString(),
                    ]),
                ]);

                // ✅ تحديث حساب الدفع
                if ($locked->user_payment_account_id) {
                    $account = UserPaymentAccount::find($locked->user_payment_account_id);

                    if ($account) {
                        $account->increment('total_withdrawn', (float) $locked->amount_from);
                        $account->increment('withdrawals_count');
                        $account->update(['last_used_at' => now()]);
                    }
                }

                // ✅ تحديث المحفظة
                $wallet = Wallet::query()
                    ->whereKey($locked->from_wallet_id)
                    ->lockForUpdate()
                    ->first();

                if ($wallet) {
                    $wallet->addWithdrawStat($locked->from_currency, (float) $locked->amount_from);

                    if ((float) $locked->commission_amount > 0) {
                        $wallet->addCommissionStat($locked->from_currency, (float) $locked->commission_amount);
                    }
                }

                $this->logFinancialSuccess('withdraw.approved', [
                    'transaction_id' => $locked->id,
                    'admin_id'       => $admin->id,
                ]);

                return ['success' => true, 'transaction' => $locked->fresh()];
            });
        } catch (\Throwable $e) {
            $this->logFinancialError('withdraw.approve', [
                'transaction_id' => $transaction->id,
            ], $e);

            return ['success' => false, 'error' => '❌ خطأ: ' . $e->getMessage()];
        }
    }

    // ============================================================
    //  الرفض (مع استرداد)
    // ============================================================

    public function reject(Transaction $transaction, User $admin, string $reason): array
    {
        try {
            return $this->runInTransaction(function () use ($transaction, $admin, $reason) {
                $locked = Transaction::query()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $locked->isPending()) {
                    return ['success' => false, 'error' => 'الطلب تمت معالجته.'];
                }

                // ✅ استرداد
                $this->refund($locked, $reason);

                $locked->update([
                    'status'      => Transaction::STATUS_REJECTED,
                    'admin_id'    => $admin->id,
                    'rejected_at' => now(),
                    'notes'       => $reason,
                    'metadata'    => array_merge($locked->metadata ?? [], [
                        'reject_reason' => $reason,
                        'rejected_by'   => $admin->id,
                    ]),
                ]);

                $this->logFinancialSuccess('withdraw.rejected', [
                    'transaction_id' => $locked->id,
                    'admin_id'       => $admin->id,
                    'reason'         => $reason,
                ]);

                return ['success' => true, 'transaction' => $locked->fresh()];
            });
        } catch (\Throwable $e) {
            $this->logFinancialError('withdraw.reject', [
                'transaction_id' => $transaction->id,
            ], $e);

            return ['success' => false, 'error' => '❌ خطأ: ' . $e->getMessage()];
        }
    }

    // ============================================================
    //  الإرسال عبر Gateway
    // ============================================================

    // ============================================================
    //  الإرسال عبر Gateway (مع توافق العملات)
    // ============================================================
    private function sendViaGateway(
        DepositMethod $method,
        Transaction $transaction,
        string $destination,
    ): array {
        try {
            $amount = (float) $transaction->amount_from;

            // ✅ توافق العملات: NSP/NPS → SYP للبوابة الخارجية
            $rawCurrency     = strtoupper((string) $method->currency);
            $gatewayCurrency = match ($rawCurrency) {
                'NSP', 'NPS' => 'SYP',
                default      => $rawCurrency,
            };

            return match ($method->gateway_type) {
                DepositMethod::GATEWAY_SYRIATEL => $this->sendViaSyriatel($method, $destination, $amount),
                DepositMethod::GATEWAY_SHAMCASH => $this->sendViaShamCash($method, $destination, $amount, $gatewayCurrency),
                default                          => [
                    'success' => false,
                    'error'   => "طريقة غير مدعومة: {$method->name}",
                ],
            };
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function sendViaSyriatel(
        DepositMethod $method,
        string $destination,
        float $amount,
    ): array {
        $sourceGsm = $method->receiver_gsm;

        if (! $sourceGsm) {
            return ['success' => false, 'error' => 'رقم المصدر (GSM) غير محدد'];
        }

        $pinCode = config('services.payment_gateway.syriatel_pin');

        if (! $pinCode) {
            return ['success' => false, 'error' => 'PIN Code غير محدد في .env'];
        }

        $response = $this->gateway->syriatelTransferCash(
            gsm: $sourceGsm,
            toGsm: $destination,
            amount: $amount,
            pinCode: $pinCode,
        );

        if (! $response || ! ($response['success'] ?? false)) {
            return [
                'success' => false,
                'error'   => ErrorMessages::friendly($response['error'] ?? $response['message'] ?? null, 'withdraw'),
                'raw'     => $response,
            ];
        }

        return ['success' => true, 'raw' => $response];
    }

    private function sendViaShamCash(
        DepositMethod $method,
        string $destination,
        float $amount,
        string $currency,
    ): array {
        $sourceAddress = $method->receiver_address;

        if (! $sourceAddress) {
            return ['success' => false, 'error' => 'عنوان المصدر غير محدد'];
        }

        $response = $this->gateway->shamcashTransfer(
            accountAddress: $sourceAddress,
            receiveKey: $destination,
            amount: $amount,
            currency: $currency,
            note: 'سحب من البوت',
        );

        if (! $response || ! ($response['success'] ?? false)) {
            return [
                'success' => false,
                'error'   => ErrorMessages::friendly($response['error'] ?? $response['message'] ?? null, 'withdraw'),
                'raw'     => $response,
            ];
        }

        return ['success' => true, 'raw' => $response];
    }

    // ============================================================
    //  الاسترداد
    // ============================================================

    private function refund(Transaction $transaction, string $reason): void
    {
        if (! $transaction->from_wallet_id) {
            return;
        }

        $wallet = Wallet::query()
            ->whereKey($transaction->from_wallet_id)
            ->lockForUpdate()
            ->first();

        if (! $wallet) {
            return;
        }

        $total = (float) $transaction->amount_from + (float) $transaction->commission_amount;

        $wallet->credit($transaction->from_currency, $total);

        $this->logFinancialSuccess('withdraw.refunded', [
            'transaction_id' => $transaction->id,
            'amount'         => $transaction->amount_from,
            'commission'     => $transaction->commission_amount,
            'total'          => $total,
            'reason'         => $reason,
        ]);
    }
}
