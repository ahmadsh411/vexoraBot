<?php

namespace App\Services;

use App\Models\DepositMethod;
use App\Models\User;
use App\Models\UserPaymentAccount;
use App\Services\Concerns\HasLockingHelpers;
use Illuminate\Support\Collection;

class UserPaymentAccountService
{
    use HasLockingHelpers;

    /**
     * البحث عن حساب أو إنشاؤه.
     */
    public function findOrCreate(
        User $user,
        DepositMethod $method,
        string $accountNumber,
        ?string $accountName = null,
    ): UserPaymentAccount {
        return $this->runInTransaction(function () use (
            $user,
            $method,
            $accountNumber,
            $accountName
        ) {
            return UserPaymentAccount::firstOrCreate(
                [
                    'user_id'           => $user->id,
                    'deposit_method_id' => $method->id,
                    'account_number'    => $accountNumber,
                ],
                [
                    'account_name'      => $accountName,
                    'total_deposited'   => 0,
                    'total_withdrawn'   => 0,
                    'available_balance' => 0,
                    'deposits_count'    => 0,
                    'withdrawals_count' => 0,
                    'is_active'         => true,
                ],
            );
        });
    }

    /**
     * حسابات المستخدم النشطة.
     */
    public function getUserAccounts(User $user): Collection
    {
        return UserPaymentAccount::forUser($user->id)
            ->active()
            ->with('depositMethod')
            ->ordered()
            ->get();
    }

    /**
     * حسابات المستخدم مع رصيد.
     */
    public function getUserAccountsWithBalance(User $user): Collection
    {
        return UserPaymentAccount::forUser($user->id)
            ->active()
            ->withBalance()
            ->with('depositMethod')
            ->ordered()
            ->get();
    }

    /**
     * إضافة إيداع آمن.
     */
    public function addDeposit(UserPaymentAccount $account, float $amount): void
    {
        $this->runInTransaction(function () use ($account, $amount) {
            $locked = UserPaymentAccount::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->addDeposit($amount);
        });
    }

    /**
     * إضافة سحب آمن.
     */
    public function addWithdrawal(UserPaymentAccount $account, float $amount): void
    {
        $this->runInTransaction(function () use ($account, $amount) {
            $locked = UserPaymentAccount::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->addWithdrawal($amount);
        });
    }

    public function findById(int $id): ?UserPaymentAccount
    {
        return UserPaymentAccount::with('depositMethod')->find($id);
    }

    public function countActive(User $user): int
    {
        return UserPaymentAccount::forUser($user->id)
            ->active()
            ->count();
    }
}
