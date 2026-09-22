<?php

namespace App\Services\PaymentGateway\Contracts;

interface PaymentGatewayInterface
{
    // ============================================================
    //  الحالة والحسابات
    // ============================================================
    public function status(): array;
    public function accounts(): array;

    // ============================================================
    //  سيرياتيل كاش — استعلام
    // ============================================================
    public function syriatelBalance(string $gsm): ?array;
    public function syriatelHistory(string $gsm, string $period = '7'): array;
    public function syriatelFindTx(string $tx, string $gsm, string $period = '7'): ?array;

    // ============================================================
    //  سيرياتيل كاش — تحويل
    // ============================================================
    public function syriatelTransferCash(
        string $gsm,
        string $toGsm,
        float $amount,
        string $pinCode,
    ): ?array;

    // ============================================================
    //  شام كاش — استعلام
    // ============================================================
    public function shamcashBalance(string $accountAddress): ?array;
    public function shamcashLogs(string $accountAddress): array;
    public function shamcashFindTx(string $tx, string $accountAddress): ?array;

    // ============================================================
    //  شام كاش — تحويل
    // ============================================================
    public function shamcashTransfer(
        string $accountAddress,
        string $receiveKey,
        float $amount,
        string $currency,
        string $note = '',
    ): ?array;
}
