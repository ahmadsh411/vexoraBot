<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Services\Concerns\HasLockingHelpers;
use Illuminate\Support\Facades\DB;

class WalletService
{
    use HasLockingHelpers;

    // ============================================================
    //  جلب المحافظ
    // ============================================================

    /**
     * المحفظة الرئيسية (أو إنشاؤها).
     */
    public function getMainWallet(): Wallet
    {
        return Wallet::firstOrCreate(
            [
                'type'    => Wallet::TYPE_MAIN,
                'user_id' => null,
            ],
            [
                'balance_nsp' => 0,
                'balance_usd' => 0,
                'is_active'   => true,
                'is_frozen'   => false,
            ],
        );
    }

    /**
     * محفظة المستخدم (أو إنشاؤها).
     */
    public function getUserWallet(User $user): Wallet
    {
        return Wallet::firstOrCreate(
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
    }

    // ============================================================
    //  ✅ العمليات المالية الآمنة (Pessimistic Locking)
    // ============================================================

    /**
     * إضافة رصيد آمن.
     */
    public function credit(
        Wallet $wallet,
        string $currency,
        float $amount,
        array $context = [],
    ): bool {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        if (! $wallet->canTransact()) {
            throw new \RuntimeException('المحفظة مجمّدة أو غير نشطة');
        }

        try {
            $this->runInTransaction(function () use ($wallet, $currency, $amount, $context) {
                // ✅ قفل وإعادة تحميل
                $locked = $this->lockAndRefresh($wallet);

                if (! $locked->canTransact()) {
                    throw new \RuntimeException('المحفظة مجمّدة عند التنفيذ');
                }

                $before = $locked->getBalance($currency);

                $locked->credit($currency, $amount);

                $after = $locked->fresh()->getBalance($currency);

                $this->logFinancialSuccess('wallet.credit', array_merge($context, [
                    'wallet_id'      => $wallet->id,
                    'user_id'        => $wallet->user_id,
                    'currency'       => $currency,
                    'amount'         => $amount,
                    'balance_before' => $before,
                    'balance_after'  => $after,
                ]));
            });

            return true;
        } catch (\Throwable $e) {
            $this->logFinancialError('wallet.credit', [
                'wallet_id' => $wallet->id,
                'currency'  => $currency,
                'amount'    => $amount,
            ], $e);

            throw $e;
        }
    }

    /**
     * خصم رصيد آمن.
     */
    public function debit(
        Wallet $wallet,
        string $currency,
        float $amount,
        array $context = [],
    ): bool {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        if (! $wallet->canTransact()) {
            throw new \RuntimeException('المحفظة مجمّدة أو غير نشطة');
        }

        try {
            $this->runInTransaction(function () use ($wallet, $currency, $amount, $context) {
                // ✅ قفل وإعادة تحميل
                $locked = $this->lockAndRefresh($wallet);

                if (! $locked->canTransact()) {
                    throw new \RuntimeException('المحفظة مجمّدة عند التنفيذ');
                }

                $before = $locked->getBalance($currency);

                // ✅ التحقق من كفاية الرصيد داخل القفل
                if ($before < $amount) {
                    throw new \RuntimeException(
                        "رصيد غير كافٍ. المتاح: {$before} {$currency}، المطلوب: {$amount}"
                    );
                }

                $locked->debit($currency, $amount);

                $after = $locked->fresh()->getBalance($currency);

                $this->logFinancialSuccess('wallet.debit', array_merge($context, [
                    'wallet_id'      => $wallet->id,
                    'user_id'        => $wallet->user_id,
                    'currency'       => $currency,
                    'amount'         => $amount,
                    'balance_before' => $before,
                    'balance_after'  => $after,
                ]));
            });

            return true;
        } catch (\Throwable $e) {
            $this->logFinancialError('wallet.debit', [
                'wallet_id' => $wallet->id,
                'currency'  => $currency,
                'amount'    => $amount,
            ], $e);

            throw $e;
        }
    }

    /**
     * تحويل بين محفظتين (نفس العملة).
     */
    public function transfer(
        Wallet $from,
        Wallet $to,
        string $currency,
        float $amount,
        array $context = [],
    ): bool {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        try {
            $this->runInTransaction(function () use ($from, $to, $currency, $amount, $context) {
                // ✅ قفل المحفظتين بترتيب ثابت (تجنب deadlock)
                $ids = [$from->id, $to->id];
                sort($ids);

                $wallets = Wallet::query()
                    ->whereIn('id', $ids)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $lockedFrom = $wallets->get($from->id);
                $lockedTo   = $wallets->get($to->id);

                if (! $lockedFrom || ! $lockedTo) {
                    throw new \RuntimeException('إحدى المحفظتين غير موجودة');
                }

                if (! $lockedFrom->canTransact() || ! $lockedTo->canTransact()) {
                    throw new \RuntimeException('إحدى المحفظتين مجمّدة');
                }

                $fromBefore = $lockedFrom->getBalance($currency);

                if ($fromBefore < $amount) {
                    throw new \RuntimeException('رصيد غير كافٍ للتحويل');
                }

                $lockedFrom->debit($currency, $amount);
                $lockedTo->credit($currency, $amount);

                $this->logFinancialSuccess('wallet.transfer', array_merge($context, [
                    'from_wallet_id' => $from->id,
                    'to_wallet_id'   => $to->id,
                    'currency'       => $currency,
                    'amount'         => $amount,
                ]));
            });

            return true;
        } catch (\Throwable $e) {
            $this->logFinancialError('wallet.transfer', [
                'from_wallet_id' => $from->id,
                'to_wallet_id'   => $to->id,
                'amount'         => $amount,
            ], $e);

            throw $e;
        }
    }

    // ============================================================
    //  الحالة (Freeze/Unfreeze)
    // ============================================================

    public function freeze(Wallet $wallet, ?string $reason = null): void
    {
        $wallet->update(['is_frozen' => true]);

        $this->logFinancialSuccess('wallet.freeze', [
            'wallet_id' => $wallet->id,
            'reason'    => $reason,
        ]);
    }

    public function unfreeze(Wallet $wallet): void
    {
        $wallet->update(['is_frozen' => false]);

        $this->logFinancialSuccess('wallet.unfreeze', [
            'wallet_id' => $wallet->id,
        ]);
    }

    // ============================================================
    //  الإحصائيات
    // ============================================================

    public function stats(Wallet $wallet): array
    {
        return [
            'balance_nsp'          => (float) $wallet->balance_nsp,
            'balance_usd'          => (float) $wallet->balance_usd,
            'total_deposit_nsp'    => (float) $wallet->total_deposit_nsp,
            'total_deposit_usd'    => (float) $wallet->total_deposit_usd,
            'total_withdraw_nsp'   => (float) $wallet->total_withdraw_nsp,
            'total_withdraw_usd'   => (float) $wallet->total_withdraw_usd,
            'total_commission_nsp' => (float) $wallet->total_commission_nsp,
            'total_commission_usd' => (float) $wallet->total_commission_usd,
            'is_frozen'            => $wallet->is_frozen,
            'is_active'            => $wallet->is_active,
        ];
    }
}
