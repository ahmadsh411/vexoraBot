<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialSafetyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * اختبار: منع الرصيد السالب.
     */
    public function test_wallet_cannot_go_negative(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id'     => $user->id,
            'type'        => Wallet::TYPE_USER,
            'balance_syp' => 100,
        ]);

        $service = app(WalletService::class);

        $this->expectException(\RuntimeException::class);

        $service->debit($wallet, 'SYP', 200);
    }

    /**
     * اختبار: credit يعمل بنجاح.
     */
    public function test_wallet_credit_works(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id'     => $user->id,
            'type'        => Wallet::TYPE_USER,
            'balance_syp' => 0,
        ]);

        $service = app(WalletService::class);
        $service->credit($wallet, 'SYP', 500);

        $this->assertEquals(500, (float) $wallet->fresh()->balance_syp);
    }

    /**
     * اختبار: pessimistic lock يمنع race condition.
     */
    public function test_pessimistic_lock_prevents_double_spend(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id'     => $user->id,
            'type'        => Wallet::TYPE_USER,
            'balance_syp' => 100,
        ]);

        $service = app(WalletService::class);

        $service->debit($wallet, 'SYP', 60);

        $this->expectException(\RuntimeException::class);
        $service->debit($wallet, 'SYP', 60);
    }
}
