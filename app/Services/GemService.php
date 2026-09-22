<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\GemBalance;
use App\Models\GemTransaction;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GemService
{
    // ═══════════════════════════════════════════════════════════
    //  الإعدادات
    // ═══════════════════════════════════════════════════════════

    public const KEY_ENABLED           = 'gems.enabled';
    public const KEY_MIN_DEPOSIT_NSP   = 'gems.min_deposit_nsp';
    public const KEY_MIN_DEPOSIT_USD   = 'gems.min_deposit_usd';
    public const KEY_EXCHANGE_MIN      = 'gems.exchange_min_gems';
    public const KEY_EXCHANGE_VALUE    = 'gems.exchange_value_nsp';
    public const KEY_WHEEL_MIN         = 'gems.wheel_min_gems';
    public const KEY_WHEEL_SPINS       = 'gems.wheel_spins';

    // ═══════════════════════════════════════════════════════════
    //  الاستعلامات
    // ═══════════════════════════════════════════════════════════

    public function isEnabled(): bool
    {
        return (bool) Setting::get(self::KEY_ENABLED, false);
    }

    public function getMinDepositNsp(): float
    {
        return max(0, (float) Setting::get(self::KEY_MIN_DEPOSIT_NSP, 1000));
    }

    public function getMinDepositUsd(): float
    {
        return max(0, (float) Setting::get(self::KEY_MIN_DEPOSIT_USD, 1));
    }

    public function getExchangeMinGems(): int
    {
        return max(1, (int) Setting::get(self::KEY_EXCHANGE_MIN, 10));
    }

    public function getExchangeValueNsp(): float
    {
        return max(0, (float) Setting::get(self::KEY_EXCHANGE_VALUE, 10000));
    }

    public function getWheelMinGems(): int
    {
        return max(1, (int) Setting::get(self::KEY_WHEEL_MIN, 5));
    }

    public function getWheelSpins(): int
    {
        return max(1, (int) Setting::get(self::KEY_WHEEL_SPINS, 1));
    }

    // ═══════════════════════════════════════════════════════════
    //  الرصيد
    // ═══════════════════════════════════════════════════════════

    public function getBalance(User $user): GemBalance
    {
        return GemBalance::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'total_earned' => 0, 'total_spent' => 0]
        );
    }

    public function getBalanceInt(User $user): int
    {
        return $this->getBalance($user)->balance;
    }

    // ═══════════════════════════════════════════════════════════
    //  اكتساب الجواهر (من الإيداع)
    // ═══════════════════════════════════════════════════════════

    /**
     * منح جواهر عند إيداع ناجح.
     *
     * @return array{awarded: bool, gems: int, reason: string}
     */
    public function awardForDeposit(Transaction $transaction): array
    {
        $result = ['awarded' => false, 'gems' => 0, 'reason' => ''];

        if (! $this->isEnabled()) {
            $result['reason'] = 'disabled';
            return $result;
        }

        $user = $transaction->user;

        if (! $user) {
            $result['reason'] = 'no_user';
            return $result;
        }

        // تجنّب التطبيق المزدوج
        $exists = GemTransaction::where('user_id', $user->id)
            ->where('source', GemTransaction::SOURCE_DEPOSIT)
            ->where('reference_id', $transaction->id)
            ->exists();

        if ($exists) {
            $result['reason'] = 'already_awarded';
            return $result;
        }

        $amount   = (float) $transaction->amount_to;
        $currency = strtoupper($transaction->to_currency);

        // احسب الحد الأدنى
        $minAmount = $currency === 'USD'
            ? $this->getMinDepositUsd()
            : $this->getMinDepositNsp();

        if ($amount < $minAmount) {
            $result['reason'] = 'below_minimum';
            return $result;
        }

        try {
            DB::transaction(function () use ($user, $transaction, &$result) {
                $balance = $this->getBalance($user);

                $balance->credit(1);

                GemTransaction::create([
                    'user_id'      => $user->id,
                    'amount'       => 1,
                    'type'         => GemTransaction::TYPE_EARN,
                    'source'       => GemTransaction::SOURCE_DEPOSIT,
                    'reference_id' => $transaction->id,
                    'notes'        => 'اكتساب جوهرة من إيداع #' . $transaction->id,
                    'metadata'     => [
                        'amount'   => $transaction->amount_to,
                        'currency' => $transaction->to_currency,
                    ],
                ]);

                $result['awarded'] = true;
                $result['gems']    = 1;
                $result['reason']  = 'ok';

                Log::info('Gem awarded for deposit', [
                    'user_id'        => $user->id,
                    'transaction_id' => $transaction->id,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to award gem', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            $result['reason'] = 'error';
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════════════
    //  الاستبدال برصيد
    // ═══════════════════════════════════════════════════════════

    /**
     * حساب قيم الاستبدال المتاحة.
     *
     * @return array{nsp: float, usd: float, rate: float}
     */
    public function calculateExchange(User $user): array
    {
        $balance = $this->getBalanceInt($user);
        $minGems = $this->getExchangeMinGems();
        $valueNsp = $this->getExchangeValueNsp();

        if ($balance < $minGems) {
            return ['nsp' => 0, 'usd' => 0, 'rate' => 0];
        }

        // NSP المباشر
        $nspValue = ($balance / $minGems) * $valueNsp;

        // سعر الصرف
        $rate = 0;
        $rateModel = ExchangeRate::between('USD', 'NSP')->where('is_active', true)->latest()->first();

        if ($rateModel) {
            $rate = (float) $rateModel->rate;
        }

        $usdValue = $rate > 0 ? $nspValue / $rate : 0;

        return [
            'nsp'  => round($nspValue, 2),
            'usd'  => round($usdValue, 2),
            'rate' => $rate,
        ];
    }

    /**
     * استبدال الجواهر برصيد.
     *
     * @return array{success: bool, amount: float, currency: string, error: string}
     */
    public function exchangeToBalance(User $user, string $currency): array
    {
        $result = ['success' => false, 'amount' => 0, 'currency' => $currency, 'error' => ''];

        if (! $this->isEnabled()) {
            $result['error'] = 'نظام الجواهر معطّل';
            return $result;
        }

        $currency = strtoupper($currency);

        if (! in_array($currency, ['NSP', 'USD'], true)) {
            $result['error'] = 'عملة غير مدعومة';
            return $result;
        }

        $balance = $this->getBalanceInt($user);
        $minGems = $this->getExchangeMinGems();

        if ($balance < $minGems) {
            $result['error'] = "الحد الأدنى {$minGems} جواهر";
            return $result;
        }

        $values = $this->calculateExchange($user);
        $amount = $currency === 'USD' ? $values['usd'] : $values['nsp'];

        if ($amount <= 0) {
            $result['error'] = 'قيمة الاستبدال غير صالحة';
            return $result;
        }

        try {
            DB::transaction(function () use ($user, $currency, $amount, $balance, &$result) {
                // ✅ 1. خصم الجواهر
                $gemBalance = $this->getBalance($user);
                $gemBalance->debit($balance);

                // ✅ 2. إضافة الرصيد للمحفظة
                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $user->id, 'type' => Wallet::TYPE_USER],
                    ['balance_nsp' => 0, 'balance_usd' => 0, 'is_active' => true]
                );

                $wallet->credit($currency, $amount);

                // ✅ 3. سجل معاملة الرصيد
                Transaction::create([
                    'user_id'       => $user->id,
                    'to_wallet_id'  => $wallet->id,
                    'type'          => 'gems_exchange',
                    'from_currency' => $currency,
                    'to_currency'   => $currency,
                    'amount_from'   => $amount,
                    'amount_to'     => $amount,
                    'status'        => Transaction::STATUS_COMPLETED,
                    'reference'     => 'gems_exchange_' . $user->id . '_' . time(),
                    'notes'         => 'استبدال ' . $balance . ' جوهرة بـ ' . number_format($amount, 2) . ' ' . $currency,
                    'completed_at'  => now(),
                ]);

                // ✅ 4. سجل حركة الجواهر
                GemTransaction::create([
                    'user_id'      => $user->id,
                    'amount'       => -$balance,
                    'type'         => GemTransaction::TYPE_SPEND,
                    'source'       => GemTransaction::SOURCE_EXCHANGE,
                    'notes'        => 'استبدال بـ ' . number_format($amount, 2) . ' ' . $currency,
                    'metadata'     => [
                        'currency' => $currency,
                        'amount'   => $amount,
                        'gems'     => $balance,
                    ],
                ]);

                $result['success']  = true;
                $result['amount']   = $amount;
                $result['currency'] = $currency;

                Log::info('Gems exchanged', [
                    'user_id'  => $user->id,
                    'gems'     => $balance,
                    'amount'   => $amount,
                    'currency' => $currency,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Gems exchange failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            $result['error'] = 'فشل الاستبدال';
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════════════
    //  فتح العجلة
    // ═══════════════════════════════════════════════════════════

    /**
     * استبدال جواهر بلفات عجلة.
     *
     * @return array{success: bool, spins: int, gems_spent: int, error: string}
     */
    public function exchangeToWheelSpins(User $user): array
    {
        $result = ['success' => false, 'spins' => 0, 'gems_spent' => 0, 'error' => ''];

        if (! $this->isEnabled()) {
            $result['error'] = 'نظام الجواهر معطّل';
            return $result;
        }

        $balance  = $this->getBalanceInt($user);
        $minGems  = $this->getWheelMinGems();
        $spins    = $this->getWheelSpins();

        if ($balance < $minGems) {
            $result['error'] = "الحد الأدنى {$minGems} جواهر";
            return $result;
        }

        try {
            DB::transaction(function () use ($user, $minGems, $spins, &$result) {
                $gemBalance = $this->getBalance($user);
                $gemBalance->debit($minGems);

                // ✅ إضافة لفات العجلة
                $wheelService = app(\App\Services\WheelService::class);
                $wheelService->grantSpins($user, $spins);

                GemTransaction::create([
                    'user_id'   => $user->id,
                    'amount'    => -$minGems,
                    'type'      => GemTransaction::TYPE_SPEND,
                    'source'    => GemTransaction::SOURCE_WHEEL,
                    'notes'     => 'فتح ' . $spins . ' لفة عجلة',
                    'metadata'  => [
                        'spins'     => $spins,
                        'gems_used' => $minGems,
                    ],
                ]);

                $result['success']     = true;
                $result['spins']       = $spins;
                $result['gems_spent']  = $minGems;

                Log::info('Gems exchanged for wheel spins', [
                    'user_id' => $user->id,
                    'spins'   => $spins,
                    'gems'    => $minGems,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Gems wheel exchange failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            $result['error'] = 'فشل الاستبدال';
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════════════
    //  منح يدوي (Admin)
    // ═══════════════════════════════════════════════════════════

    public function grantManual(User $user, int $gems, string $reason = ''): bool
    {
        if ($gems <= 0) return false;

        try {
            DB::transaction(function () use ($user, $gems, $reason) {
                $this->getBalance($user)->credit($gems);

                GemTransaction::create([
                    'user_id' => $user->id,
                    'amount'  => $gems,
                    'type'    => GemTransaction::TYPE_EARN,
                    'source'  => GemTransaction::SOURCE_ADMIN,
                    'notes'   => $reason ?: 'منح يدوي من الأدمن',
                ]);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('Manual gem grant failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }
}
