<?php

namespace App\Services;

use App\Models\DepositMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Concerns\HasLockingHelpers;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

class TransactionService
{
    use HasLockingHelpers;

    public function __construct(
        private readonly WalletService $walletService,
        private readonly ExchangeRateService $exchangeRateService,
    ) {}

    // ============================================================
    //  Creation
    // ============================================================

    /**
     * إنشاء طلب إيداع.
     */
    public function createDeposit(
        User $user,
        float $amount,
        string $currency = Wallet::CURRENCY_NSP,
        array $metadata = [],
    ): Transaction {
        $wallet = $this->walletService->getUserWallet($user);

        return Transaction::create([
            'user_id'      => $user->id,
            'to_wallet_id' => $wallet->id,
            'type'         => $currency === Wallet::CURRENCY_USD
                ? Transaction::TYPE_DEPOSIT_USD
                : Transaction::TYPE_DEPOSIT,
            'to_currency'  => $currency,
            'amount_to'    => $amount,
            'status'       => Transaction::STATUS_PENDING,
            'metadata'     => $metadata,
        ]);
    }

    /**
     * إنشاء طلب سحب (مع حجز فوري).
     */
    public function createWithdraw(
        User $user,
        float $amount,
        string $currency = Wallet::CURRENCY_NSP,
        array $metadata = [],
    ): Transaction {
        return $this->runInTransaction(function () use ($user, $amount, $currency, $metadata) {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', Wallet::TYPE_USER)
                ->lockForUpdate()
                ->firstOrFail();

            $commission = (float) ($metadata['fee_amount'] ?? 0);
            $totalToDeduct = $amount + $commission;

            if (! $wallet->hasEnough($currency, $totalToDeduct)) {
                throw new \RuntimeException('رصيد غير كافٍ');
            }

            // ✅ حجز المبلغ (خصم فوري)
            $wallet->debit($currency, $totalToDeduct);

            // ✅ سجّل الحجز
            return Transaction::create([
                'user_id'        => $user->id,
                'from_wallet_id' => $wallet->id,
                'type'           => $currency === Wallet::CURRENCY_USD
                    ? Transaction::TYPE_WITHDRAW_USD
                    : Transaction::TYPE_WITHDRAW,
                'from_currency'  => $currency,
                'amount_from'    => $amount,
                'commission_amount' => $commission,
                'status'         => Transaction::STATUS_PENDING,
                'metadata'       => array_merge($metadata, [
                    'total_deducted' => $totalToDeduct,
                ]),
            ]);
        });
    }

    /**
     * إنشاء تحويل بين العملات.
     */
    public function createExchange(
        User $user,
        float $amount,
        string $fromCurrency,
        string $toCurrency,
    ): Transaction {
        return $this->runInTransaction(function () use ($user, $amount, $fromCurrency, $toCurrency) {
            $conversion = $this->exchangeRateService->convert(
                from: $fromCurrency,
                to: $toCurrency,
                amount: $amount,
            );

            if (! $conversion) {
                throw new \RuntimeException('سعر الصرف غير متوفر');
            }

            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', Wallet::TYPE_USER)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $wallet->hasEnough($fromCurrency, $amount)) {
                throw new \RuntimeException('رصيد غير كافٍ');
            }

            // ✅ خصم فوري
            $wallet->debit($fromCurrency, $amount);

            return Transaction::create([
                'user_id'           => $user->id,
                'from_wallet_id'    => $wallet->id,
                'to_wallet_id'      => $wallet->id,
                'type'              => Transaction::TYPE_EXCHANGE,
                'from_currency'     => $fromCurrency,
                'to_currency'       => $toCurrency,
                'amount_from'       => $amount,
                'amount_to'         => $conversion['amount_to'],
                'exchange_rate'     => $conversion['rate'],
                'commission_amount' => $conversion['commission'],
                'status'            => Transaction::STATUS_COMPLETED,
                'completed_at'      => now(),
            ]);
        });
    }

    /**
     * تعديل رصيد من الأدمن.
     */
    public function adminAdjustment(
        User $user,
        float $amount,
        string $currency,
        User $admin,
        bool $isCredit = true,
        ?string $notes = null,
    ): Transaction {
        $wallet = $this->walletService->getUserWallet($user);

        return Transaction::create([
            'user_id'        => $user->id,
            'to_wallet_id'   => $isCredit ? $wallet->id : null,
            'from_wallet_id' => ! $isCredit ? $wallet->id : null,
            'type'           => $isCredit
                ? Transaction::TYPE_ADMIN_CREDIT
                : Transaction::TYPE_ADMIN_DEBIT,
            'from_currency'  => ! $isCredit ? $currency : null,
            'to_currency'    => $isCredit ? $currency : null,
            'amount_from'    => ! $isCredit ? $amount : 0,
            'amount_to'      => $isCredit ? $amount : 0,
            'status'         => Transaction::STATUS_PENDING,
            'admin_id'       => $admin->id,
            'notes'          => $notes,
        ]);
    }

    // ============================================================
    //  Approval Flow
    // ============================================================

    /**
     * الموافقة على عملية (والتنفيذ الآمن).
     */
    public function approve(Transaction $transaction, User $admin): bool
    {
        try {
            return $this->runInTransaction(function () use ($transaction, $admin) {
                // ✅ قفل العملية
                $locked = Transaction::query()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $locked->canBeApproved()) {
                    throw new \RuntimeException('لا يمكن الموافقة على هذه العملية');
                }

                $locked->update([
                    'status'      => Transaction::STATUS_APPROVED,
                    'admin_id'    => $admin->id,
                    'approved_at' => now(),
                ]);

                // ✅ تنفيذ
                $this->execute($locked);

                $locked->update([
                    'status'       => Transaction::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);

                $this->logFinancialSuccess('transaction.approved', [
                    'transaction_id' => $locked->id,
                    'reference'      => $locked->reference,
                    'admin_id'       => $admin->id,
                    'type'           => $locked->type,
                ]);

                // ✅ إشعار قناة Transactions
                $this->notifyChannelAboutApproval($locked, $admin);

                return true;
            });
        } catch (\Throwable $e) {
            $this->logFinancialError('transaction.approve', [
                'transaction_id' => $transaction->id,
            ], $e);

            // ✅ حاول تسجيل الفشل (بدون رمي استثناء)
            try {
                $transaction->update([
                    'status'   => Transaction::STATUS_FAILED,
                    'failed_at' => now(),
                    'notes'    => ($transaction->notes ?? '') . "\nخطأ: " . $e->getMessage(),
                ]);
            } catch (\Throwable $inner) {
                // تجاهل
            }

            throw $e;
        }
    }

    /**
     * رفض عملية.
     */
    public function reject(
        Transaction $transaction,
        User $admin,
        ?string $reason = null,
    ): bool {
        return $this->runInTransaction(function () use ($transaction, $admin, $reason) {
            $locked = Transaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->canBeRejected()) {
                throw new \RuntimeException('لا يمكن رفض هذه العملية');
            }

            // ✅ إذا كانت سحب → استرداد المبلغ
            if ($locked->isWithdrawal() && $locked->from_wallet_id) {
                $this->refundWithdraw($locked, $reason);
            }

            $locked->update([
                'status'      => Transaction::STATUS_REJECTED,
                'admin_id'    => $admin->id,
                'rejected_at' => now(),
                'notes'       => $reason,
            ]);

            $this->logFinancialSuccess('transaction.rejected', [
                'transaction_id' => $locked->id,
                'reference'      => $locked->reference,
                'admin_id'       => $admin->id,
                'reason'         => $reason,
            ]);

            // ✅ إشعار قناة Transactions
            $this->notifyChannelAboutRejection($locked, $admin, $reason ?? '');

            return true;
        });
    }

    /**
     * إلغاء عملية (من المستخدم).
     */
    public function cancel(Transaction $transaction): bool
    {
        return $this->runInTransaction(function () use ($transaction) {
            $locked = Transaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->canBeCancelled()) {
                throw new \RuntimeException('لا يمكن إلغاء عملية غير معلّقة');
            }

            // ✅ استرداد إن كانت سحب
            if ($locked->isWithdrawal() && $locked->from_wallet_id) {
                $this->refundWithdraw($locked, 'إلغاء من المستخدم');
            }

            $locked->update(['status' => Transaction::STATUS_CANCELLED]);

            return true;
        });
    }

    // ============================================================
    //  Execution
    // ============================================================

    /**
     * تنفيذ العملية حسب النوع.
     */
    protected function execute(Transaction $transaction): void
    {
        match ($transaction->type) {
            Transaction::TYPE_DEPOSIT,
            Transaction::TYPE_DEPOSIT_USD,
            Transaction::TYPE_ADMIN_CREDIT      => $this->executeCredit($transaction),

            Transaction::TYPE_WITHDRAW,
            Transaction::TYPE_WITHDRAW_USD,
            Transaction::TYPE_ADMIN_DEBIT       => $this->executeDebit($transaction),

            default => null,
        };
    }

    /**
     * تنفيذ إيداع / إضافة.
     */
    protected function executeCredit(Transaction $transaction): void
    {
        $toWallet = Wallet::query()
            ->whereKey($transaction->to_wallet_id)
            ->lockForUpdate()
            ->first();

        if (! $toWallet) {
            $toWallet = Wallet::query()
                ->where('user_id', $transaction->user_id)
                ->where('type', Wallet::TYPE_USER)
                ->lockForUpdate()
                ->first();
        }

        if (! $toWallet) {
            $toWallet = Wallet::create([
                'user_id'            => $transaction->user_id,
                'type'               => Wallet::TYPE_USER,
                'balance_nsp'        => 0,
                'balance_usd'        => 0,
                'total_deposit_nsp'  => 0,
                'total_deposit_usd'  => 0,
                'total_withdraw_nsp' => 0,
                'total_withdraw_usd' => 0,
                'is_active'          => true,
                'is_frozen'          => false,
            ]);
        }

        if (! $transaction->to_wallet_id) {
            $transaction->update(['to_wallet_id' => $toWallet->id]);
        }

        $currency = $transaction->to_currency;
        $amount   = (float) $transaction->amount_to;

        $toWallet->credit($currency, $amount);
        $toWallet->addDepositStat($currency, $amount);

        // ✅ إضافة العمولة للمحفظة الرئيسية
        if ((float) $transaction->commission_amount > 0) {
            $toWallet->addCommissionStat($currency, (float) $transaction->commission_amount);
        }
    }

    /**
     * تنفيذ سحب / خصم.
     */
    protected function executeDebit(Transaction $transaction): void
    {
        $fromWallet = Wallet::query()
            ->whereKey($transaction->from_wallet_id)
            ->lockForUpdate()
            ->first();

        if (! $fromWallet) {
            throw new \RuntimeException('محفظة المصدر غير موجودة');
        }

        $currency = $transaction->from_currency;
        $amount   = (float) $transaction->amount_from;

        if (! $fromWallet->hasEnough($currency, $amount)) {
            throw new \RuntimeException('رصيد غير كافٍ عند التنفيذ');
        }

        // ✅ المبلغ محجوز مسبقاً (فور createWithdraw)
        // لكن في admin_adjustment، نحتاج خصم فعلي
        if ($transaction->type === Transaction::TYPE_ADMIN_DEBIT) {
            $fromWallet->debit($currency, $amount);
        }

        $fromWallet->addWithdrawStat($currency, $amount);

        // ✅ إضافة العمولة للمحفظة الرئيسية
        if ((float) $transaction->commission_amount > 0) {
            $fromWallet->addCommissionStat($currency, (float) $transaction->commission_amount);
        }
    }

    // ============================================================
    //  Refund
    // ============================================================

    /**
     * استرداد مبلغ سحب مرفوض.
     */
    protected function refundWithdraw(Transaction $transaction, ?string $reason = null): void
    {
        if (! $transaction->from_wallet_id) {
            return;
        }

        $wallet = Wallet::query()
            ->whereKey($transaction->from_wallet_id)
            ->lockForUpdate()
            ->first();

        if (! $wallet) {
            $this->logFinancialError('transaction.refund', [
                'transaction_id' => $transaction->id,
                'error'          => 'Wallet not found',
            ], new \RuntimeException('Wallet not found'));

            return;
        }

        // ✅ استرداد المبلغ + العمولة
        $total = (float) $transaction->amount_from + (float) $transaction->commission_amount;

        $wallet->credit($transaction->from_currency, $total);

        $this->logFinancialSuccess('transaction.refunded', [
            'transaction_id' => $transaction->id,
            'wallet_id'      => $wallet->id,
            'currency'       => $transaction->from_currency,
            'amount'         => $transaction->amount_from,
            'commission'     => $transaction->commission_amount,
            'total'          => $total,
            'reason'         => $reason,
        ]);

        // ✅ إشعار قناة Transactions
        $this->notifyChannelAboutRefund($transaction, $total, $reason ?? '');
    }

    // ============================================================
    //  📢 Notifications — Transactions Channel
    // ============================================================

    /**
     * إشعار قناة Transactions — الموافقة على معاملة.
     */
    protected function notifyChannelAboutApproval(Transaction $transaction, User $admin): void
    {
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            $bot = app(Nutgram::class);

            $typeLabel = match ($transaction->type) {
                Transaction::TYPE_DEPOSIT,
                Transaction::TYPE_DEPOSIT_USD     => '📥 إيداع',
                Transaction::TYPE_WITHDRAW,
                Transaction::TYPE_WITHDRAW_USD    => '📤 سحب',
                Transaction::TYPE_ADMIN_CREDIT    => '➕ إضافة رصيد',
                Transaction::TYPE_ADMIN_DEBIT     => '➖ خصم رصيد',
                default                            => '💱 معاملة',
            };

            $amount = (float) ($transaction->amount_from ?: $transaction->amount_to);
            $currency = $transaction->from_currency ?: $transaction->to_currency;

            $text = implode("\n", [
                '✅ <b>تمت الموافقة على معاملة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🆔 <b>العملية:</b> <code>#' . $transaction->id . '</code>',
                '🔖 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
                '📋 <b>النوع:</b> ' . $typeLabel,
                '💰 <b>المبلغ:</b> <b>' . number_format($amount, 2) . ' ' . $currency . '</b>',
                '',
                '👮 <b>بواسطة:</b> <code>' . $admin->username . '</code>',
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            $notificationService->notifyTransactionsChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify transactions channel about approval', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * إشعار قناة Transactions — الرفض.
     */
    protected function notifyChannelAboutRejection(
        Transaction $transaction,
        User $admin,
        string $reason,
    ): void {
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            $bot = app(Nutgram::class);

            $user = $transaction->user;

            $typeLabel = match ($transaction->type) {
                Transaction::TYPE_DEPOSIT,
                Transaction::TYPE_DEPOSIT_USD     => '📥 إيداع',
                Transaction::TYPE_WITHDRAW,
                Transaction::TYPE_WITHDRAW_USD    => '📤 سحب',
                Transaction::TYPE_ADMIN_CREDIT    => '➕ إضافة رصيد',
                Transaction::TYPE_ADMIN_DEBIT     => '➖ خصم رصيد',
                default                            => '💱 معاملة',
            };

            $amount = (float) ($transaction->amount_from ?: $transaction->amount_to);
            $currency = $transaction->from_currency ?: $transaction->to_currency;

            $text = implode("\n", [
                '❌ <b>تم رفض معاملة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🆔 <b>العملية:</b> <code>#' . $transaction->id . '</code>',
                '🔖 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
                '📋 <b>النوع:</b> ' . $typeLabel,
                '',
                '👤 <b>المستخدم:</b> <code>' . ($user?->username ?? '—') . '</code>',
                '💰 <b>المبلغ:</b> <b>' . number_format($amount, 2) . ' ' . $currency . '</b>',
                '',
                '📝 <b>السبب:</b>',
                '<i>' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</i>',
                '',
                '👮 <b>بواسطة:</b> <code>' . $admin->username . '</code>',
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            $notificationService->notifyTransactionsChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify transactions channel about rejection', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * إشعار قناة Transactions — الاسترداد.
     */
    protected function notifyChannelAboutRefund(
        Transaction $transaction,
        float $total,
        string $reason,
    ): void {
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            $bot = app(Nutgram::class);

            $user = $transaction->user;

            $text = implode("\n", [
                '💸 <b>تم استرداد مبلغ</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🆔 <b>العملية:</b> <code>#' . $transaction->id . '</code>',
                '🔖 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
                '',
                '👤 <b>المستخدم:</b> <code>' . ($user?->username ?? '—') . '</code>',
                '💰 <b>المبلغ المسترد:</b> <b>' . number_format($total, 2) . ' ' . $transaction->from_currency . '</b>',
                '',
                '📝 <b>السبب:</b>',
                '<i>' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</i>',
                '',
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            $notificationService->notifyTransactionsChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify transactions channel about refund', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  Helpers
    // ============================================================

    /**
     * إحصائيات عامة.
     */
    public function stats(): array
    {
        return [
            'pending_count'   => Transaction::pending()->count(),
            'completed_count' => Transaction::completed()->count(),
            'today_deposits'  => (float) Transaction::completed()
                ->deposits()
                ->whereDate('completed_at', today())
                ->sum('amount_to'),
            'today_withdrawals' => (float) Transaction::completed()
                ->withdrawals()
                ->whereDate('completed_at', today())
                ->sum('amount_from'),
        ];
    }
}
