<?php

namespace App\Services;

use App\Models\DepositMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Concerns\HasLockingHelpers;
use App\Services\PaymentGateway\Drivers\DepositVerificationService;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class DepositService
{
    use HasLockingHelpers;

    public function __construct(
        private readonly DepositValidationService $validator,
        private readonly DepositVerificationService $verifier,
    ) {}

    // ============================================================
    //  إنشاء طلب إيداع
    // ============================================================

    /**
     * إنشاء طلب إيداع كامل.
     *
     * @return array{success: bool, transaction?: Transaction, error?: string}
     */
    public function createRequest(
        User $user,
        DepositMethod $method,
        float $amount,
        string $transactionId,
        ?string $proofFile = null,
    ): array {
        // ✅ 1. التحقق
        $validation = $this->validator->validate($method, $amount);

        if (! $validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        try {
            return $this->runInTransaction(function () use (
                $user,
                $method,
                $amount,
                $transactionId,
                $proofFile
            ) {
                // ✅ 2. المحفظة
                $wallet = Wallet::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'type'    => Wallet::TYPE_USER,
                    ],
                    [
                        'balance_nsp' => 0,
                        'balance_usd' => 0,
                        'is_active'   => true,
                        'is_frozen'   => false,
                    ],
                );

                // ✅ 3. حساب العمولة
                $commission = $method->calculateCommission($amount);
                $amountAfterCommission = $amount - $commission;

                // ✅ 4. استخراج بيانات التحقق
                $receiverGsm     = $method->receiver_gsm;
                $receiverAddress = $method->receiver_address;

                // Fallback آمن
                if (! $receiverGsm && ! $receiverAddress) {
                    $fallback = (string) ($method->account_number ?? '');

                    if (preg_match('/^09\d{8}$/', $fallback)) {
                        $receiverGsm = $fallback;
                    }

                    if (preg_match('/^[a-f0-9]{10,}$/i', $fallback)) {
                        $receiverAddress = $fallback;
                    }
                }

                // ✅ 5. إنشاء Transaction
                $transaction = Transaction::create([
                    'user_id'          => $user->id,
                    'to_wallet_id'     => $wallet->id,
                    'type'             => $method->currency === 'USD'
                        ? Transaction::TYPE_DEPOSIT_USD
                        : Transaction::TYPE_DEPOSIT,
                    'from_currency'    => $method->currency,
                    'to_currency'      => $method->currency,
                    'amount_from'      => $amount,
                    'amount_to'        => $amountAfterCommission,
                    'commission_amount' => $commission,
                    'status'           => Transaction::STATUS_PENDING,
                    'ichancy_transaction_id' => $transactionId,
                    'proof_file'       => $proofFile,
                    'notes'            => 'إيداع عبر ' . $method->name,
                    'metadata'         => [
                        'method_id'        => $method->id,
                        'method_code'      => $method->code,
                        'method_name'      => $method->name,
                        'method_icon'      => $method->icon,
                        'method_currency'  => $method->currency,
                        'receiver_gsm'     => $receiverGsm,
                        'receiver_address' => $receiverAddress,
                    ],
                ]);

                $this->logFinancialSuccess('deposit.created', [
                    'transaction_id' => $transaction->id,
                    'user_id'        => $user->id,
                    'amount'         => $amount,
                    'method'         => $method->code,
                ]);

                return ['success' => true, 'transaction' => $transaction];
            });
        } catch (\Throwable $e) {
            $this->logFinancialError('deposit.create', [
                'user_id' => $user->id,
                'method'  => $method->id,
                'amount'  => $amount,
            ], $e);

            return ['success' => false, 'error' => 'فشل إنشاء الطلب: ' . $e->getMessage()];
        }
    }

    // ============================================================
    //  معالجة الإيداع (تحقق + موافقة)
    // ============================================================

    /**
     * معالجة الإيداع بعد الإنشاء (تحقق تلقائي).
     */
    public function processAfterCreate(Transaction $transaction): array
    {
        try {
            $result = $this->verifier->verifyAndApprove($transaction);

            return $result;
        } catch (\Throwable $e) {
            $this->logFinancialError('deposit.process', [
                'transaction_id' => $transaction->id,
            ], $e);

            return [
                'verified' => false,
                'status'   => 'error',
                'message'  => $e->getMessage(),
            ];
        }
    }

    // ============================================================
    //  الموافقة اليدوية
    // ============================================================

    /**
     * موافقة يدوية من الأدمن.
     */
    public function approveManually(Transaction $transaction, User $admin): bool
    {
        if (! $transaction->isPending()) {
            throw new \RuntimeException('الطلب تمت معالجته مسبقاً');
        }

        try {
            return $this->runInTransaction(function () use ($transaction, $admin) {
                $locked = Transaction::query()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $locked->isPending()) {
                    throw new \RuntimeException('الطلب تمت معالجته مسبقاً');
                }

                // ✅ تحديث الحالة
                $locked->update([
                    'status'      => Transaction::STATUS_APPROVED,
                    'admin_id'    => $admin->id,
                    'approved_at' => now(),
                ]);

                // ✅ إضافة الرصيد
                $this->creditUserWallet($locked);

                // 🎁 مكافأة الإيداع
                $bonusResult = $this->applyDepositBonus($locked);

                // 💎 منح جوهرة (نظام الجواهر)
                $gemResult = $this->awardGem($locked);

                // ✅ إنهاء المعاملة
                $locked->update([
                    'status'       => Transaction::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);

                $this->logFinancialSuccess('deposit.approved_manually', [
                    'transaction_id' => $locked->id,
                    'admin_id'       => $admin->id,
                    'bonus_applied'  => $bonusResult['applied'] ?? false,
                    'bonus_amount'   => $bonusResult['amount'] ?? 0,
                ]);

                // 🎉 إشعار المستخدم بالمكافأة (خارج الـ transaction)
                if (($bonusResult['applied'] ?? false) === true) {
                    $this->notifyUserAboutBonus($locked, $bonusResult);
                }

                return true;
            });
        } catch (\Throwable $e) {
            $this->logFinancialError('deposit.approve_manually', [
                'transaction_id' => $transaction->id,
            ], $e);

            return false;
        }
    }

    /**
     * رفض يدوي.
     */
    public function rejectManually(
        Transaction $transaction,
        User $admin,
        string $reason,
    ): bool {
        if (! $transaction->isPending()) {
            throw new \RuntimeException('الطلب تمت معالجته مسبقاً');
        }

        return $this->runInTransaction(function () use ($transaction, $admin, $reason) {
            $locked = Transaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPending()) {
                throw new \RuntimeException('الطلب تمت معالجته مسبقاً');
            }

            $locked->update([
                'status'      => Transaction::STATUS_REJECTED,
                'admin_id'    => $admin->id,
                'rejected_at' => now(),
                'notes'       => $reason,
            ]);

            $this->logFinancialSuccess('deposit.rejected', [
                'transaction_id' => $locked->id,
                'admin_id'       => $admin->id,
                'reason'         => $reason,
            ]);

            return true;
        });
    }

    // ============================================================
    //  Private Helpers
    // ============================================================

    /**
     * إضافة الرصيد للمستخدم.
     */
    private function creditUserWallet(Transaction $transaction): void
    {
        $wallet = Wallet::query()
            ->whereKey($transaction->to_wallet_id)
            ->lockForUpdate()
            ->first();

        if (! $wallet) {
            $wallet = Wallet::query()
                ->where('user_id', $transaction->user_id)
                ->where('type', Wallet::TYPE_USER)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $currency = $transaction->to_currency;
        $amount   = (float) $transaction->amount_to;

        $wallet->credit($currency, $amount);
        $wallet->addDepositStat($currency, $amount);

        // ✅ العمولة للمحفظة الرئيسية
        if ((float) $transaction->commission_amount > 0) {
            $wallet->addCommissionStat($currency, (float) $transaction->commission_amount);
        }
    }

    /**
     * 💎 منح جوهرة عند الإيداع
     */
    private function awardGem(Transaction $transaction): array
    {
        try {
            return app(\App\Services\GemService::class)
                ->awardForDeposit($transaction);
        } catch (\Throwable $e) {
            Log::warning('Gem award hook failed', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);

            return ['awarded' => false, 'gems' => 0, 'reason' => 'error'];
        }
    }
    /**
     * 🎁 تطبيق مكافأة الإيداع.
     */
    private function applyDepositBonus(Transaction $transaction): array
    {
        try {
            return app(DepositBonusService::class)
                ->applyOnDeposit($transaction);
        } catch (\Throwable $e) {
            Log::warning('Deposit bonus hook failed', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);

            return ['applied' => false, 'amount' => 0, 'percent' => 0];
        }
    }

    /**
     * 🎉 إشعار المستخدم بالمكافأة.
     */
    private function notifyUserAboutBonus(Transaction $transaction, array $bonusResult): void
    {
        try {
            $user = $transaction->user;

            if (! $user || ! $user->telegram_id) {
                return;
            }

            $bonus    = (float) ($bonusResult['amount'] ?? 0);
            $percent  = (int) ($bonusResult['percent'] ?? 0);
            $deposit  = (float) $transaction->amount_to;
            $currency = $transaction->to_currency ?: 'NSP';
            $total    = $deposit + $bonus;

            $text = implode("\n", [
                '🎉 <b>مبروك!</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '✅ <b>تم اعتماد إيداعك</b>',
                '',
                '💰 <b>المبلغ المودع:</b>',
                '└── <b>' . number_format($deposit, 2) . ' ' . $currency . '</b>',
                '',
                '🎁 <b>مكافأة الإيداع (' . $percent . '%):</b>',
                '└── <b>+' . number_format($bonus, 2) . ' ' . $currency . '</b>',
                '',
                '💎 <b>الإجمالي المضاف:</b>',
                '└── <b>' . number_format($total, 2) . ' ' . $currency . '</b>',
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '💚 شكراً لثقتك بنا!',
            ]);

            // ✅ 1. إرسال مباشر (إن كان البوت متاحاً)
            try {
                $bot = app(Nutgram::class);

                $bot->sendMessage(
                    text: $text,
                    chat_id: $user->telegram_id,
                    parse_mode: 'HTML',
                );

                Log::info('Deposit bonus notification sent', [
                    'user_id'        => $user->id,
                    'transaction_id' => $transaction->id,
                    'bonus'          => $bonus,
                ]);

                return;
            } catch (\Throwable $e) {
                Log::warning('Direct bonus notification failed, trying service', [
                    'error' => $e->getMessage(),
                ]);
            }

            // ✅ 2. Fallback: استخدم NotificationService
            app(NotificationService::class)->send(
                app(Nutgram::class),
                $user,
                $text,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to notify user about deposit bonus', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
