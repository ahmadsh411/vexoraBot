<?php

namespace App\Services\PaymentGateway;

use App\Services\PaymentGateway\Contracts\PaymentGatewayInterface;
use App\Services\PaymentGateway\Drivers\LivePaymentGateway;
use App\Services\PaymentGateway\Drivers\MockPaymentGateway;

class PaymentGatewayService
{
    protected PaymentGatewayInterface $driver;

    public function __construct()
    {
        $this->driver = $this->resolveDriver();
    }

    protected function resolveDriver(): PaymentGatewayInterface
    {
        return match (config('services.payment_gateway.driver', 'live')) {
            'mock'  => new MockPaymentGateway(),
            default => new LivePaymentGateway(),
        };
    }

    // ============================================================
    //  Public API
    // ============================================================

    public function status(): array
    {
        return $this->driver->status();
    }

    public function accounts(): array
    {
        return $this->driver->accounts();
    }

    public function syriatelBalance(string $gsm): ?array
    {
        return $this->driver->syriatelBalance($gsm);
    }

    public function syriatelHistory(string $gsm, string $period = '7'): array
    {
        return $this->driver->syriatelHistory($gsm, $period);
    }

    public function syriatelFindTx(string $tx, string $gsm, string $period = '7'): ?array
    {
        return $this->driver->syriatelFindTx($tx, $gsm, $period);
    }

    public function syriatelTransferCash(
        string $gsm,
        string $toGsm,
        float $amount,
        string $pinCode,
    ): ?array {
        return $this->driver->syriatelTransferCash($gsm, $toGsm, $amount, $pinCode);
    }

    public function shamcashBalance(string $accountAddress): ?array
    {
        return $this->driver->shamcashBalance($accountAddress);
    }

    public function shamcashLogs(string $accountAddress): array
    {
        return $this->driver->shamcashLogs($accountAddress);
    }

    public function shamcashFindTx(string $tx, string $accountAddress): ?array
    {
        return $this->driver->shamcashFindTx($tx, $accountAddress);
    }

    public function shamcashTransfer(
        string $accountAddress,
        string $receiveKey,
        float $amount,
        string $currency,
        string $note = '',
    ): ?array {
        return $this->driver->shamcashTransfer($accountAddress, $receiveKey, $amount, $currency, $note);
    }
}
