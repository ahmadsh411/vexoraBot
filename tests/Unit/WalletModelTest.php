<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_enough_returns_true_when_balance_sufficient(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id'     => $user->id,
            'type'        => Wallet::TYPE_USER,
            'balance_syp' => 1000,
        ]);

        $this->assertTrue($wallet->hasEnough('SYP', 500));
        $this->assertTrue($wallet->hasEnough('SYP', 1000));
        $this->assertFalse($wallet->hasEnough('SYP', 1001));
    }

    public function test_get_balance_returns_correct_value(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id'     => $user->id,
            'type'        => Wallet::TYPE_USER,
            'balance_syp' => 1000,
            'balance_usd' => 50,
        ]);

        $this->assertEquals(1000, $wallet->getBalance('SYP'));
        $this->assertEquals(50, $wallet->getBalance('USD'));
    }
}
