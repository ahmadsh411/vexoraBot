<?php

namespace App\Services\IChancy;

use App\Models\IChancyAccount;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IChancyAccountService
{
    public function __construct(
        private readonly IChancyService $ichancy,
    ) {}

    // ============================================================
    //  ➕ إنشاء حساب في IChancy
    // ============================================================
    public function createForUser(User $user, string $plainPassword): ?IChancyAccount
    {
        if ($user->ichancyAccount) {
            return $user->ichancyAccount;
        }

        try {
            $player = $this->ichancy->registerAndGetPlayer(
                login: $user->username,
                password: $plainPassword,
            );

            if (! $player) {
                Log::warning('IChancyAccountService: registration failed', [
                    'user_id'  => $user->id,
                    'username' => $user->username,
                ]);
                return null;
            }

            $account = IChancyAccount::create([
                'user_id'                    => $user->id,
                'ichancy_player_id'          => (string) ($player['playerId'] ?? ''),
                'ichancy_username'           => $user->username,
                'ichancy_password_encrypted' => encrypt($plainPassword),
                'currency'                   => $player['currency'] ?? 'NSP',
                'balance_cache'              => 0,
                'is_active'                  => true,
            ]);

            Log::info('IChancyAccountService: account created', [
                'user_id'   => $user->id,
                'player_id' => $player['playerId'] ?? null,
            ]);

            return $account;
        } catch (\Throwable $e) {
            Log::error('IChancyAccountService: exception', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ============================================================
    //  💰 مزامنة الرصيد
    // ============================================================
    public function syncBalance(IChancyAccount $account): bool
    {
        $balance = $this->ichancy->getPlayerBalance($account->ichancy_player_id);

        if ($balance === null) {
            return false;
        }

        $account->update([
            'balance_cache'  => $balance,
            'last_synced_at' => now(),
        ]);

        return true;
    }

    // ============================================================
    //  💰 جلب رصيد المستخدم (Cache 5 دقائق)
    // ============================================================
    public function getBalanceForUser(User $user, int $cacheSeconds = 300): ?array
    {
        $account = $user->ichancyAccount;

        if (! $account || ! $account->ichancy_player_id) {
            return null;
        }

        $cacheKey = 'ichancy_balance_user_' . $user->id;

        // ✅ Cache
        if ($cacheSeconds > 0) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $balance = $this->ichancy->getPlayerBalance($account->ichancy_player_id);

            if ($balance === null) {
                // ✅ إذا فشل الجلب → رجّع آخر قيمة مخزّنة بدل null
                if ($account->last_synced_at) {
                    return [
                        'balance'   => (float) $account->balance_cache,
                        'currency'  => $account->currency ?? 'NSP',
                        'player_id' => $account->ichancy_player_id,
                        'username'  => $account->ichancy_username,
                        'stale'     => true,
                    ];
                }
                return null;
            }

            $result = [
                'balance'   => (float) $balance,
                'currency'  => $account->currency ?? 'NSP',
                'player_id' => $account->ichancy_player_id,
                'username'  => $account->ichancy_username,
            ];

            // ✅ حدّث الـ cache في DB
            $account->update([
                'balance_cache'  => $result['balance'],
                'last_synced_at' => now(),
            ]);

            if ($cacheSeconds > 0) {
                Cache::put($cacheKey, $result, now()->addSeconds($cacheSeconds));
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning('IChancyAccountService: getBalanceForUser failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            // ✅ إذا فشل → رجّع آخر قيمة مخزّنة
            if ($account->last_synced_at) {
                return [
                    'balance'   => (float) $account->balance_cache,
                    'currency'  => $account->currency ?? 'NSP',
                    'player_id' => $account->ichancy_player_id,
                    'username'  => $account->ichancy_username,
                    'stale'     => true,
                ];
            }

            return null;
        }
    }

    // ============================================================
    //  🔍 هل المستخدم مربوط؟
    // ============================================================
    public function hasAccount(User $user): bool
    {
        return $user->ichancyAccount()->exists();
    }
}
