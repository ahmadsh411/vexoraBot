<?php

namespace App\Services\PaymentGateway\Drivers;

use App\Models\DepositMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DepositService;
use App\Services\NotificationService;
use App\Services\PaymentGateway\PaymentGatewayService;
use App\Services\WalletService;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use Illuminate\Support\Facades\Cache;

class DepositVerificationService
{
    public function __construct(
        private readonly PaymentGatewayService $gateway,
        private readonly WalletService $walletService,
    ) {}

    // ============================================================
    //  التحقق الأساسي
    // ============================================================

    public function verifyTransaction(Transaction $transaction): array
    {
        $txId = $transaction->ichancy_transaction_id;

        if (! $txId) {
            return $this->fail('no_id', 'لا يوجد رقم عملية');
        }

        $methodId = $transaction->metadata['method_id'] ?? null;

        if (! $methodId) {
            return $this->fail('no_method', 'لا توجد طريقة إيداع');
        }

        $method = DepositMethod::find($methodId);

        if (! $method) {
            return $this->fail('method_not_found', 'طريقة الإيداع غير موجودة');
        }

        if (! $method->supportsAutoVerify()) {
            return $this->fail('no_auto_verify', 'هذه الطريقة لا تدعم التحقق التلقائي');
        }

        return match ($method->gateway_type) {
            DepositMethod::GATEWAY_SYRIATEL => $this->verifySyriatel($transaction, $txId, $method),
            DepositMethod::GATEWAY_SHAMCASH => $this->verifyShamCash($transaction, $txId, $method),
            default                          => $this->fail('unsupported', 'البوابة غير مدعومة'),
        };
    }

    // ============================================================
    //  سيرياتيل
    // ============================================================

    protected function verifySyriatel(
        Transaction $transaction,
        string $txId,
        DepositMethod $method,
    ): array {
        $gsm = $method->receiver_gsm;

        if (! $gsm || ! preg_match('/^09\d{8}$/', $gsm)) {
            return $this->fail('no_gsm', 'رقم GSM المستقبل غير صالح');
        }

        try {
            $result = $this->gateway->syriatelFindTx($txId, $gsm, '30');
        } catch (\Throwable $e) {
            Log::error('Syriatel API error', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
            return $this->fail('api_error', 'فشل الاتصال بـ API سيرياتيل');
        }

        if (! $result) {
            return $this->fail('not_found', 'رقم العملية غير موجود في سيرياتيل كاش');
        }

        return $this->validateAmount($transaction, $result);
    }

    // ============================================================
    //  شام كاش
    // ============================================================

    protected function verifyShamCash(
        Transaction $transaction,
        string $txId,
        DepositMethod $method,
    ): array {
        $address = $method->receiver_address;

        if (! $address || ! preg_match('/^[a-f0-9]{10,}$/i', $address)) {
            return $this->fail('no_address', 'عنوان شام كاش غير صالح');
        }

        try {
            $result = $this->gateway->shamcashFindTx($txId, $address);
        } catch (\Throwable $e) {
            Log::error('ShamCash API error', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
            return $this->fail('api_error', 'فشل الاتصال بـ API شام كاش');
        }

        if (! $result) {
            return $this->fail('not_found', 'رقم العملية غير موجود في شام كاش');
        }

        return $this->validateAmount($transaction, $result);
    }

    // ============================================================
    //  التحقق من المبلغ
    // ============================================================

    protected function validateAmount(Transaction $transaction, array $result): array
    {
        $txData = $this->extractTxData($result);

        $receivedAmount = (float) (
            $txData['amount']
            ?? $txData['sum']
            ?? $txData['value']
            ?? $txData['total']
            ?? $txData['money']
            ?? 0
        );

        $expectedAmount = (float) $transaction->amount_from;

        $currency = $txData['currency']
            ?? $txData['currency_code']
            ?? $result['currency']
            ?? null;

        // ✅ توافق العملات (SYP/NSP/NPS = نفس العملة بأسماء مختلفة)
        if ($currency) {
            $currencyGroups = [
                'SYP' => ['SYP', 'NSP', 'NPS'],
                'NSP' => ['NSP', 'SYP', 'NPS'],
                'NPS' => ['NPS', 'NSP', 'SYP'],
                'USD' => ['USD'],
            ];

            $apiCurrency       = strtoupper((string) $currency);
            $transactionCurrency = strtoupper((string) $transaction->to_currency);

            $allowedForTx = $currencyGroups[$transactionCurrency] ?? [$transactionCurrency];

            if (! in_array($apiCurrency, $allowedForTx, true)) {
                return $this->fail(
                    'currency_mismatch',
                    "العملة ({$currency}) لا تطابق ({$transaction->to_currency})"
                );
            }
        }

        if ($receivedAmount <= 0) {
            return $this->fail('amount_not_found', 'لم يتم العثور على المبلغ');
        }

        $tolerancePercent = (float) config('services.payment_gateway.amount_tolerance_percent', 0);
        $tolerance = $expectedAmount * ($tolerancePercent / 100);
        $minAcceptable = $expectedAmount - $tolerance;

        if ($receivedAmount < $minAcceptable) {
            return $this->fail(
                'amount_mismatch',
                "المبلغ المُحوَّل (" . number_format($receivedAmount, 2) . ") أقل من المتوقع (" . number_format($expectedAmount, 2) . ")"
            );
        }

        return [
            'verified' => true,
            'status'   => 'verified',
            'message'  => 'تم التحقق بنجاح',
            'amount'   => $receivedAmount,
            'raw'      => $result,
        ];
    }

    // ============================================================
    //  ✅ verifyAndApprove — Auto بالكامل
    // ============================================================

    public function verifyAndApprove(Transaction $transaction): array
    {
        $result = $this->verifyTransaction($transaction);

        Log::info('Deposit verification', [
            'transaction_id' => $transaction->id,
            'verified'       => $result['verified'],
            'status'         => $result['status'],
            'message'        => $result['message'] ?? null,
        ]);

        // ✅ نجح → موافقة تلقائية
        if ($result['verified']) {
            return $this->autoApprove($transaction, $result);
        }

        // ❌ فشل → رفض تلقائي
        $this->autoReject($transaction, $result['message']);

        $result['auto_rejected'] = true;

        Log::info('Deposit auto-rejected', [
            'transaction_id' => $transaction->id,
            'status'         => $result['status'],
            'reason'         => $result['message'],
        ]);

        return $result;
    }

    // ============================================================
    //  ✅ الموافقة التلقائية
    // ============================================================

    protected function autoApprove(Transaction $transaction, array $result): array
    {
        try {
            $systemAdmin = $this->getSystemAdmin();

            if (! $systemAdmin) {
                Log::warning('No admin found for auto-approval', [
                    'transaction_id' => $transaction->id,
                ]);

                $result['auto_approved'] = false;
                return $result;
            }

            $approved = app(DepositService::class)
                ->approveManually($transaction, $systemAdmin);

            $result['auto_approved'] = (bool) $approved;

            if ($approved) {
                // ✅ معالجة الإحالة (مكافآت + تحديث إحصائيات)
                try {
                    app(\App\Services\ReferralService::class)->processDeposit($transaction);

                    Log::info('Referral processed for deposit (auto)', [
                        'transaction_id' => $transaction->id,
                        'user_id'        => $transaction->user_id,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Failed to process referral for auto-approved deposit', [
                        'transaction_id' => $transaction->id,
                        'error'          => $e->getMessage(),
                    ]);
                }

                $this->awardWheelSpin($transaction);

                // ✅ إشعار قناة Transactions
                $this->notifyChannelAutoApproved($transaction, $systemAdmin);
            }
        } catch (\Throwable $e) {
            Log::error('Auto-approval failed', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);

            $result['auto_approved'] = false;
        }

        return $result;
    }

    // ============================================================
    //  ❌ الرفض التلقائي
    // ============================================================

    protected function autoReject(Transaction $transaction, string $reason): void
    {
        try {
            $systemAdmin = $this->getSystemAdmin();

            if (! $systemAdmin) {
                Log::warning('No admin found for auto-reject', [
                    'transaction_id' => $transaction->id,
                ]);
                return;
            }

            $transaction->update([
                'status'      => Transaction::STATUS_REJECTED,
                'admin_id'    => $systemAdmin->id,
                'rejected_at' => now(),
                'notes'       => 'رفض تلقائي: ' . $reason,
                'metadata'    => array_merge($transaction->metadata ?? [], [
                    'auto_rejected'        => true,
                    'auto_rejected_at'     => now()->toDateTimeString(),
                    'auto_rejected_reason' => $reason,
                ]),
            ]);

            $this->notifyUserRejected($transaction, $reason);
            // ✅ إشعار قناة Transactions
            $this->notifyChannelAutoRejected($transaction, $reason);

            Log::info('Deposit auto-rejected', [
                'transaction_id' => $transaction->id,
                'reason'         => $reason,
            ]);
        } catch (\Throwable $e) {
            Log::error('Auto-reject failed', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  📢 إشعار المستخدم بالرفض
    // ============================================================

    protected function notifyUserRejected(Transaction $transaction, string $reason): void
    {
        $user = $transaction->user;

        if (! $user || ! $user->telegram_id) {
            return;
        }

        try {
            $text = implode("\n", [
                '⚡ <b>VEXORA</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '❌ <b>تم رفض طلب الإيداع</b>',
                '',
                '💰 <b>المبلغ:</b> <b>'
                    . number_format((float) $transaction->amount_from, 2)
                    . ' ' . $transaction->to_currency . '</b>',
                '',
                '📊 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📝 <b>السبب:</b>',
                '<i>' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</i>',
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '💡 <b>تحقق من:</b>',
                '• رقم العملية',
                '• المبلغ المُحوَّل',
                '• الطريقة المُختارة',
            ]);

            app(Nutgram::class)->sendMessage(
                text: $text,
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify user about auto-reject', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  📢 إشعار قناة Transactions — الرفض التلقائي
    // ============================================================

    protected function notifyChannelAutoRejected(Transaction $transaction, string $reason): void
    {
        try {
            $user = $transaction->user;

            $text = implode("\n", [
                '❌ <b>رفض تلقائي لإيداع</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🆔 <b>العملية:</b> <code>#' . $transaction->id . '</code>',
                '🔖 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
                '',
                '👤 <b>المستخدم:</b> <code>' . ($user?->username ?? '—') . '</code>',
                '💰 <b>المبلغ:</b> <b>' . number_format((float) $transaction->amount_from, 2) . ' ' . $transaction->to_currency . '</b>',
                '',
                '📝 <b>السبب:</b>',
                '<i>' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</i>',
                '',
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            app(NotificationService::class)->notifyTransactionsChannel(
                app(Nutgram::class),
                $text,
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about auto-reject', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  📢 إشعار قناة Transactions — الموافقة التلقائية
    // ============================================================

    protected function notifyChannelAutoApproved(Transaction $transaction, User $systemAdmin): void
    {
        try {
            $user = $transaction->user;

            $text = implode("\n", [
                '✅ <b>موافقة تلقائية على إيداع</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🆔 <b>العملية:</b> <code>#' . $transaction->id . '</code>',
                '🔖 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
                '',
                '👤 <b>المستخدم:</b> <code>' . ($user?->username ?? '—') . '</code>',
                '💰 <b>المبلغ:</b> <b>' . number_format((float) $transaction->amount_from, 2) . ' ' . $transaction->to_currency . '</b>',
                '',
                '👮 <b>بواسطة:</b> النظام (تلقائي)',
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            app(NotificationService::class)->notifyTransactionsChannel(
                app(Nutgram::class),
                $text,
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about auto-approve', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  🎡 منح لفة عجلة الحظ
    // ============================================================

    private function awardWheelSpin(Transaction $transaction): void
    {
        try {
            $user = $transaction->user;

            if (! $user) {
                return;
            }

            $currency = $transaction->to_currency;
            $amount   = (float) $transaction->amount_to;

            if ($amount <= 0) {
                return;
            }

            $wheelService = app(\App\Services\WheelService::class);
            $result = $wheelService->awardDepositSpin($user, $amount, $currency);

            if ($result['awarded'] ?? false) {
                Log::info('Wheel: spin awarded', [
                    'user_id'        => $user->id,
                    'transaction_id' => $transaction->id,
                    'spins_gained'   => $result['spins_gained'] ?? 0,
                ]);

                $this->notifyUserAboutWheelSpin($user, $result);
            }
        } catch (\Throwable $e) {
            Log::warning('Wheel: award spin failed', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    private function notifyUserAboutWheelSpin(User $user, array $result): void
    {
        if (! $user->telegram_id) {
            return;
        }

        try {
            $spinsGained = $result['spins_gained'] ?? 0;
            $totalSpins  = $result['total_spins'] ?? 0;

            $text = implode("\n", [
                '🎉 <b>مبروك!</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🎡 حصلت على <b>' . $spinsGained . '</b> لفة عجلة حظ جديدة!',
                '',
                '💰 <b>اللفات المتاحة:</b> <b>' . $totalSpins . '</b>',
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🎁 اذهب إلى 🎡 عجلة الحظ ولف العجلة!',
            ]);

            app(Nutgram::class)->sendMessage(
                text: $text,
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
                reply_markup: \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup::make()
                    ->addRow(
                        \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                            text: '🎡 افتح العجلة',
                            callback_data: 'user.wheel',
                        ),
                    ),
            );
        } catch (\Throwable $e) {
            Log::warning('Wheel notify failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  Helpers
    // ============================================================

    protected function extractTxData(array $result): array
    {
        return $result['transaction']
            ?? $result['data']['transaction']
            ?? $result['data']
            ?? $result['items'][0]
            ?? $result;
    }

    protected function fail(string $status, string $message): array
    {
        return [
            'verified' => false,
            'status'   => $status,
            'message'  => $message,
            'raw'      => null,
        ];
    }


    /**
     * جلب المسؤول النظامي (cached 15 دقيقة)
     */
    protected function getSystemAdmin(): ?User
    {
        return Cache::remember('system_admin_user', 900, function () {
            return User::where('is_admin', true)
                ->where('is_super_admin', true)
                ->orderBy('id')
                ->first()
                ?? User::where('is_admin', true)
                ->orderBy('id')
                ->first();
        });
    }

    /**
     * إبطال كاش المسؤول
     */
    public static function forgetSystemAdminCache(): void
    {
        Cache::forget('system_admin_user');
    }
}
