<?php

namespace App\Services\PaymentGateway\Drivers;

use App\Services\PaymentGateway\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;

class MockPaymentGateway implements PaymentGatewayInterface
{
    public function status(): array
    {
        return [
            'ok'      => true,
            'message' => '[MOCK] API is working',
            'raw'     => ['success' => true],
        ];
    }

    public function accounts(): array
    {
        return [
            'syriatel' => [
                ['gsm' => '0933000000', 'cash_code' => '123456'],
            ],
            'shamcash' => [
                ['account_address' => '251aTESTADDRESS'],
            ],
        ];
    }

    public function syriatelBalance(string $gsm): ?array
    {
        return [
            'gsm'       => $gsm,
            'cash_code' => '123456',
            'balance'   => '150000',
        ];
    }

    public function syriatelHistory(string $gsm, string $period = '7'): array
    {
        return [
            [
                'transaction_no' => 'MOCK-TX-' . rand(1000, 9999),
                'date'           => now()->format('Y-m-d H:i:s'),
                'from'           => '0933111111',
                'to'             => $gsm,
                'amount'         => '50000',
            ],
        ];
    }

    public function syriatelFindTx(string $tx, string $gsm, string $period = '7'): ?array
    {
        Log::info('[MOCK] syriatelFindTx', ['tx' => $tx, 'gsm' => $gsm]);

        if ($tx === '0000') {
            return null;
        }

        return [
            'found' => true,
            'transaction' => [
                'transaction_no' => $tx,
                'date'           => now()->format('Y-m-d H:i:s'),
                'from'           => '0933111111',
                'to'             => $gsm,
                'amount'         => '50000',
            ],
            'account' => [
                'gsm'       => $gsm,
                'cash_code' => '123456',
            ],
        ];
    }

    public function syriatelTransferCash(
        string $gsm,
        string $toGsm,
        float $amount,
        string $pinCode,
    ): ?array {
        if ($pinCode === '0000') {
            return [
                'success' => false,
                'error'   => 'PIN Code غير صحيح',
            ];
        }

        return [
            'success' => true,
            'data'    => [
                'source_account' => ['gsm' => $gsm],
                'beneficiary'    => ['gsm' => $toGsm],
                'amount'         => (string) $amount,
                'billcode'       => 'MOCK' . strtoupper(substr(md5((string) microtime(true)), 0, 8)),
                'message'        => 'تم التحويل بنجاح (MOCK)',
            ],
        ];
    }

    public function shamcashBalance(string $accountAddress): ?array
    {
        return [
            'account_address' => $accountAddress,
            'balances'        => [
                ['currency' => 'SYP', 'balance' => 20980],
                ['currency' => 'USD', 'balance' => 0],
            ],
        ];
    }

    public function shamcashLogs(string $accountAddress): array
    {
        return [
            [
                'tran_id'   => 'MOCK-' . rand(1000, 9999),
                'from_name' => 'Client Name',
                'to_name'   => 'Your Name',
                'currency'  => 'SYP',
                'amount'    => 25000,
                'datetime'  => now()->format('Y-m-d H:i:s'),
                'account'   => $accountAddress,
                'note'      => 'شحن رصيد',
            ],
        ];
    }

    public function shamcashFindTx(string $tx, string $accountAddress): ?array
    {
        Log::info('[MOCK] shamcashFindTx', ['tx' => $tx, 'address' => $accountAddress]);

        if ($tx === '0000') {
            return null;
        }

        return [
            'found' => true,
            'transaction' => [
                'tran_id'   => $tx,
                'from_name' => 'Client Name',
                'to_name'   => 'Your Name',
                'currency'  => 'SYP',
                'amount'    => 25000,
                'datetime'  => now()->format('Y-m-d H:i:s'),
                'account'   => $accountAddress,
                'note'      => 'شحن رصيد',
            ],
            'account' => [
                'account_address' => $accountAddress,
            ],
        ];
    }

    public function shamcashTransfer(
        string $accountAddress,
        string $receiveKey,
        float $amount,
        string $currency,
        string $note = '',
    ): ?array {
        if (str_starts_with($receiveKey, '000')) {
            return [
                'success' => false,
                'error'   => 'عنوان المستلم غير صالح',
            ];
        }

        return [
            'success' => true,
            'data'    => [
                'message'        => "تم تحويل {$amount} {$currency} بنجاح ✅ (MOCK)",
                'source_account' => ['account_address' => $accountAddress],
                'recipient'      => ['address' => $receiveKey, 'userName' => 'Mock User'],
                'transfer'       => [
                    'amount'   => (string) $amount,
                    'currency' => $currency,
                    'note'     => $note ?: 'API transfer test',
                ],
            ],
        ];
    }
}
