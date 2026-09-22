<?php

namespace App\Services\IChancy;

use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

class IChancyTransferService
{
    public function __construct(
        private readonly IChancyService $ichancy,
    ) {}

    // ============================================================
    //  💰 الإعدادات
    // ============================================================
    public function getSettings(): array
    {
        return [
            'enabled'      => (bool) Setting::get('ichancy.enabled', true),
            'min_deposit'  => (int)  Setting::get('ichancy.min_deposit', 100),
            'max_deposit'  => (int)  Setting::get('ichancy.max_deposit', 1000000),
            'min_withdraw' => (int)  Setting::get('ichancy.min_withdraw', 100),
            'max_withdraw' => (int)  Setting::get('ichancy.max_withdraw', 1000000),
            'currency'     => 'NSP',
        ];
    }

    public function getUserWalletBalance(User $user): float
    {
        $wallet = $user->wallet;
        return $wallet ? (float) $wallet->balance_nsp : 0.0;
    }

    // ============================================================
    //  💰 جلب رصيد الكاشير الحقيقي
    // ============================================================
    public function getCashierBalance(): ?float
    {
        try {
            $response = $this->ichancy->getAgentAllWallets();

            if (! $response || ! ($response['status'] ?? false)) {
                return null;
            }

            $wallet = $response['result'][0] ?? null;

            if (! $wallet) {
                return null;
            }

            // ✅ currentWallet = الرصيد الحقيقي للكاشير
            return (float) ($wallet['currentWallet'] ?? 0);
        } catch (\Throwable $e) {
            Log::error('getCashierBalance failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ============================================================
    //  💰 شحن IChancy من المحفظة
    //  (IChancy تحوّل NSP → NPS تلقائياً: 1 NSP = 100 NPS)
    // ============================================================
    public function deposit(User $user, float $amountNsp): array
    {
        $cfg = $this->getSettings();

        if (! $cfg['enabled']) {
            return ['success' => false, 'error' => 'خدمة IChancy معطلة حالياً.'];
        }

        if ($amountNsp < $cfg['min_deposit']) {
            return ['success' => false, 'error' => 'الحد الأدنى: ' . number_format($cfg['min_deposit']) . ' NSP'];
        }

        if ($amountNsp > $cfg['max_deposit']) {
            return ['success' => false, 'error' => 'الحد الأقصى: ' . number_format($cfg['max_deposit']) . ' NSP'];
        }

        $account = $user->ichancyAccount;
        if (! $account || ! $account->ichancy_player_id) {
            return ['success' => false, 'error' => 'لا يوجد حساب IChancy مرتبط.'];
        }

        $wallet = Wallet::where('user_id', $user->id)
            ->where('type', Wallet::TYPE_USER)
            ->first();

        if (! $wallet) {
            return ['success' => false, 'error' => 'المحفظة غير موجودة.'];
        }

        if ((float) $wallet->balance_nsp < $amountNsp) {
            return ['success' => false, 'error' => 'رصيد المحفظة غير كافٍ.'];
        }

        // ═══════════════════════════════════════════════════════════
        //  ⚡ فحص رصيد الكاشير قبل الشحن (منع المحاولات الفاشلة)
        // ═══════════════════════════════════════════════════════════
        $cashierBalance = $this->getCashierBalance();

        if ($cashierBalance === null) {
            Log::warning('IChancy deposit: cannot fetch cashier balance', [
                'user_id' => $user->id,
                'amount'  => $amountNsp,
            ]);

            return [
                'success' => false,
                'error'   => 'cashier_check_failed',
                'message' => 'تعذّر التحقق من رصيد الخدمة. يرجى المحاولة لاحقاً.',
            ];
        }

        if ($cashierBalance < $amountNsp) {
            // 🔔 إشعار عاجل للقناة العامة
            $this->notifyInsufficientCashierBalance(
                $user,
                $amountNsp,
                $cashierBalance,
            );

            Log::warning('IChancy deposit: cashier balance insufficient', [
                'user_id'        => $user->id,
                'amount'         => $amountNsp,
                'cashier_balance' => $cashierBalance,
            ]);

            return [
                'success' => false,
                'error'   => 'cashier_insufficient_balance',
                'message' => 'عذراً، الخدمة غير متاحة مؤقتاً. يرجى المحاولة بعد قليل.',
                'details' => [
                    'required'  => $amountNsp,
                    'available' => $cashierBalance,
                ],
            ];
        }

        // ═══════════════════════════════════════════════════════════
        //  ✅ الرصيد كافٍ — تنفيذ الشحن
        // ═══════════════════════════════════════════════════════════
        try {
            return DB::transaction(function () use ($user, $wallet, $account, $amountNsp) {
                $transaction = Transaction::create([
                    'reference'      => 'ICH-DEP-' . strtoupper(Str::random(10)),
                    'user_id'        => $user->id,
                    'from_wallet_id' => $wallet->id,
                    'type'           => Transaction::TYPE_ICHANCY_DEPOSIT,
                    'from_currency'  => 'NSP',
                    'to_currency'    => 'NSP',
                    'amount_from'    => $amountNsp,
                    'amount_to'      => $amountNsp,
                    'status'         => Transaction::STATUS_PENDING,
                    'notes'          => "شحن IChancy: {$amountNsp} NSP",
                    'metadata'       => [
                        'ichancy_player_id' => $account->ichancy_player_id,
                        'ichancy_username'  => $account->ichancy_username,
                        'amount_nsp'        => $amountNsp,
                    ],
                ]);

                // خصم NSP من محفظة المستخدم
                $wallet->decrement('balance_nsp', $amountNsp);

                // ✅ إرسال NSP مباشرة — IChancy تحوّل داخلياً إلى NPS
                $result = $this->ichancy->depositToPlayer(
                    playerId: $account->ichancy_player_id,
                    amount: $amountNsp,
                    currency: 'NSP',
                );

                if (! $result || ! ($result['status'] ?? false)) {
                    $wallet->increment('balance_nsp', $amountNsp);

                    $transaction->update([
                        'status'    => Transaction::STATUS_FAILED,
                        'failed_at' => now(),
                        'notes'     => 'فشل الشحن في IChancy',
                        'metadata'  => array_merge($transaction->metadata ?? [], [
                            'response' => $result,
                        ]),
                    ]);

                    return ['success' => false, 'error' => 'فشل الشحن في IChancy. تم استرداد المبلغ.'];
                }

                $transaction->update([
                    'status'       => Transaction::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'metadata'     => array_merge($transaction->metadata ?? [], [
                        'ichancy_response' => $result,
                    ]),
                ]);

                Cache::forget('ichancy_balance_user_' . $user->id);

                Log::info('IChancy deposit success', [
                    'user_id' => $user->id,
                    'nsp'     => $amountNsp,
                    'player'  => $account->ichancy_player_id,
                ]);

                return ['success' => true, 'transaction' => $transaction];
            });
        } catch (\Throwable $e) {
            Log::error('IChancy deposit exception', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'خطأ: ' . $e->getMessage()];
        }
    }

    // ============================================================
    //  💸 سحب من IChancy إلى المحفظة
    //  (IChancy تحوّل NPS → NSP تلقائياً: 100 NPS = 1 NSP)
    // ============================================================
    public function withdraw(User $user, float $amountNsp): array
    {
        $cfg = $this->getSettings();

        if (! $cfg['enabled']) {
            return ['success' => false, 'error' => 'خدمة IChancy معطلة حالياً.'];
        }

        if ($amountNsp < $cfg['min_withdraw']) {
            return ['success' => false, 'error' => 'الحد الأدنى: ' . number_format($cfg['min_withdraw']) . ' NSP'];
        }

        if ($amountNsp > $cfg['max_withdraw']) {
            return ['success' => false, 'error' => 'الحد الأقصى: ' . number_format($cfg['max_withdraw']) . ' NSP'];
        }

        $account = $user->ichancyAccount;
        if (! $account || ! $account->ichancy_player_id) {
            return ['success' => false, 'error' => 'لا يوجد حساب IChancy مرتبط.'];
        }

        // فحص رصيد IChancy
        $balance = $this->ichancy->getPlayerBalance($account->ichancy_player_id);

        if ($balance === null) {
            return ['success' => false, 'error' => 'تعذّر جلب رصيد IChancy.'];
        }

        if ($balance < $amountNsp) {
            return ['success' => false, 'error' => 'رصيد IChancy غير كافٍ. المتاح: ' . number_format($balance, 2) . ' NSP'];
        }

        try {
            return DB::transaction(function () use ($user, $account, $amountNsp) {
                $wallet = Wallet::where('user_id', $user->id)
                    ->where('type', Wallet::TYPE_USER)
                    ->lockForUpdate()
                    ->first();

                if (! $wallet) {
                    return ['success' => false, 'error' => 'المحفظة غير موجودة.'];
                }

                $transaction = Transaction::create([
                    'reference'     => 'ICH-WIT-' . strtoupper(Str::random(10)),
                    'user_id'       => $user->id,
                    'to_wallet_id'  => $wallet->id,
                    'type'          => Transaction::TYPE_ICHANCY_WITHDRAW,
                    'from_currency' => 'NSP',
                    'to_currency'   => 'NSP',
                    'amount_from'   => $amountNsp,
                    'amount_to'     => $amountNsp,
                    'status'        => Transaction::STATUS_PENDING,
                    'notes'         => "سحب من IChancy: {$amountNsp} NSP",
                    'metadata'      => [
                        'ichancy_player_id' => $account->ichancy_player_id,
                        'ichancy_username'  => $account->ichancy_username,
                        'amount_nsp'        => $amountNsp,
                    ],
                ]);

                // ✅ إرسال NSP مباشرة — IChancy تحوّل داخلياً من NPS
                $result = $this->ichancy->withdrawFromPlayer(
                    playerId: $account->ichancy_player_id,
                    amount: $amountNsp,
                    currency: 'NSP',
                );

                if (! $result || ! ($result['status'] ?? false)) {
                    $transaction->update([
                        'status'    => Transaction::STATUS_FAILED,
                        'failed_at' => now(),
                        'notes'     => 'فشل السحب من IChancy',
                        'metadata'  => array_merge($transaction->metadata ?? [], [
                            'response' => $result,
                        ]),
                    ]);

                    return ['success' => false, 'error' => 'فشل السحب من IChancy.'];
                }

                // إضافة NSP للمحفظة
                $wallet->increment('balance_nsp', $amountNsp);

                $transaction->update([
                    'status'       => Transaction::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'metadata'     => array_merge($transaction->metadata ?? [], [
                        'ichancy_response' => $result,
                    ]),
                ]);

                Cache::forget('ichancy_balance_user_' . $user->id);

                Log::info('IChancy withdraw success', [
                    'user_id' => $user->id,
                    'nsp'     => $amountNsp,
                    'player'  => $account->ichancy_player_id,
                ]);

                return ['success' => true, 'transaction' => $transaction];
            });
        } catch (\Throwable $e) {
            Log::error('IChancy withdraw exception', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'خطأ: ' . $e->getMessage()];
        }
    }

    // ============================================================
    //  🔔 إشعار القناة عند نقص رصيد الكاشير
    // ============================================================
    private function notifyInsufficientCashierBalance(
        User $user,
        float $requiredAmount,
        float $cashierBalance,
    ): void {
        try {
            // ✅ Cache: لا ترسل أكثر من مرة كل 5 دقائق
            $cacheKey = 'cashier_insufficient_alert';

            if (Cache::has($cacheKey)) {
                return;
            }

            Cache::put($cacheKey, true, now()->addMinutes(5));

            $bot     = app(Nutgram::class);
            $deficit = max(0, $requiredAmount - $cashierBalance);

            $text = implode("\n", [
                '🚨 <b>محاولة شحن فاشلة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '❌ <b>السبب:</b> رصيد الكاشير غير كافٍ',
                '',
                '👤 <b>المستخدم:</b>',
                '├── <code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
                '└── ID: <code>#' . $user->id . '</code>',
                '',
                '💰 <b>المبلغ المطلوب:</b>',
                '└── <code>' . number_format($requiredAmount, 2) . ' NSP</code>',
                '',
                '💵 <b>رصيد الكاشير:</b>',
                '└── <code>' . number_format($cashierBalance, 2) . ' NSP</code>',
                '',
                '⚠️ <b>العجز:</b> <code>' . number_format($deficit, 2) . ' NSP</code>',
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '💳 <b>يرجى شحن الكاشير فوراً!</b>',
                '📅 ' . now()->format('Y-m-d H:i:s'),
            ]);

            app(NotificationService::class)->notifyGeneralChannel($bot, $text);

            Log::warning('Cashier balance insufficient - alert sent', [
                'user_id'   => $user->id,
                'required'  => $requiredAmount,
                'available' => $cashierBalance,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to notify insufficient cashier balance', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
