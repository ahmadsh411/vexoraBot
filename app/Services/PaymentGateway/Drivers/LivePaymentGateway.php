<?php

namespace App\Services\PaymentGateway\Drivers;

use App\Services\PaymentGateway\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LivePaymentGateway implements PaymentGatewayInterface
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.payment_gateway.base_url'), '/');
        $this->apiKey  = (string) config('services.payment_gateway.api_key');
        $this->timeout = (int) config('services.payment_gateway.timeout', 15);
    }

    // ============================================================
    //  1. الحالة
    // ============================================================
    public function status(): array
    {
        $response = $this->get(['resource' => 'status']);

        if (! $response || ! ($response['success'] ?? false)) {
            return [
                'ok'      => false,
                'message' => $response['error'] ?? 'فشل الاتصال',
                'raw'     => $response,
            ];
        }

        return [
            'ok'      => true,
            'message' => $response['message'] ?? 'API is working',
            'raw'     => $response,
        ];
    }

    // ============================================================
    //  2. الحسابات
    // ============================================================
    public function accounts(): array
    {
        $response = $this->get([
            'resource' => 'accounts',
            'action'   => 'list',
        ]);

        if (! $response || ! ($response['success'] ?? false)) {
            return [];
        }

        return $response['data'] ?? [];
    }

    // ============================================================
    //  3. رصيد سيرياتيل
    // ============================================================
    public function syriatelBalance(string $gsm): ?array
    {
        $response = $this->get([
            'resource' => 'syriatel',
            'action'   => 'balance',
            'gsm'      => $gsm,
        ]);

        if (! $response || ! ($response['success'] ?? false)) {
            return null;
        }

        return $response['data'] ?? null;
    }

    // ============================================================
    //  4. سجل سيرياتيل
    // ============================================================
    public function syriatelHistory(string $gsm, string $period = '7'): array
    {
        $response = $this->get([
            'resource' => 'syriatel',
            'action'   => 'history',
            'gsm'      => $gsm,
            'period'   => $period,
        ]);

        if (! $response || ! ($response['success'] ?? false)) {
            return [];
        }

        return $response['data']['items'] ?? [];
    }

    // ============================================================
    //  5. البحث عن عملية سيرياتيل
    // ============================================================
    public function syriatelFindTx(string $tx, string $gsm, string $period = '7'): ?array
    {
        $response = $this->get([
            'resource' => 'syriatel',
            'action'   => 'find_tx',
            'tx'       => $tx,
            'gsm'      => $gsm,
            'period'   => $period,
        ]);

        if (! $response || ! ($response['success'] ?? false)) {
            return null;
        }

        $data = $response['data'] ?? [];

        if (! ($data['found'] ?? false)) {
            return null;
        }

        return $data;
    }

    // ============================================================
    //  6. تحويل سيرياتيل
    // ============================================================
    public function syriatelTransferCash(
        string $gsm,
        string $toGsm,
        float $amount,
        string $pinCode,
    ): ?array {
        try {
            $response = $this->clientNoRetry()
                ->asForm()
                ->post(
                    $this->baseUrl . '?resource=syriatel&action=transfer_cash',
                    [
                        'gsm'      => $gsm,
                        'to_gsm'   => $toGsm,
                        'amount'   => (string) $amount,
                        'pin_code' => $pinCode,
                    ],
                );

            $json = $response->json();

            if ($response->status() === 429) {
                $retryAfter = $json['retry_after'] ?? 60;

                Log::warning('Syriatel transfer rate limited', [
                    'retry_after' => $retryAfter,
                ]);

                return [
                    'success'     => false,
                    'error'       => "تم تجاوز الحد المسموح. حاول بعد {$retryAfter} ثانية.",
                    'retry_after' => $retryAfter,
                    'rate_limit'  => true,
                ];
            }

            if (! $response->successful() || ! ($json['success'] ?? false)) {
                $errorMessage = $json['error']
                    ?? $json['message']
                    ?? $response->body()
                    ?? 'فشل غير معروف';

                return [
                    'success' => false,
                    'error'   => $errorMessage,
                    'raw'     => $json,
                ];
            }

            return $json;
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => 'فشل الاتصال: ' . $e->getMessage(),
            ];
        }
    }

    // ============================================================
    //  7. رصيد شام كاش
    // ============================================================
    public function shamcashBalance(string $accountAddress): ?array
    {
        $response = $this->get([
            'resource'        => 'shamcash',
            'action'          => 'balance',
            'account_address' => $accountAddress,
        ]);

        if (! $response || ! ($response['success'] ?? false)) {
            return null;
        }

        return $response['data'] ?? null;
    }

    // ============================================================
    //  8. سجل شام كاش
    // ============================================================
    public function shamcashLogs(string $accountAddress): array
    {
        $response = $this->get([
            'resource'        => 'shamcash',
            'action'          => 'logs',
            'account_address' => $accountAddress,
        ]);

        if (! $response || ! ($response['success'] ?? false)) {
            return [];
        }

        return $response['data']['items'] ?? [];
    }

    // ============================================================
    //  9. البحث عن عملية شام كاش
    // ============================================================
    public function shamcashFindTx(string $tx, string $accountAddress): ?array
    {
        $response = $this->get([
            'resource'        => 'shamcash',
            'action'          => 'find_tx',
            'tx'              => $tx,
            'account_address' => $accountAddress,
        ]);

        if (! $response || ! ($response['success'] ?? false)) {
            return null;
        }

        $data = $response['data'] ?? [];

        if (! ($data['found'] ?? false)) {
            return null;
        }

        return $data;
    }

    // ============================================================
    //  10. تحويل شام كاش
    // ============================================================
    public function shamcashTransfer(
        string $accountAddress,
        string $receiveKey,
        float $amount,
        string $currency,
        string $note = '',
    ): ?array {
        try {
            $response = $this->clientNoRetry()
                ->asForm()
                ->post(
                    $this->baseUrl . '?resource=shamcash&action=transfer',
                    [
                        'account_address' => $accountAddress,
                        'receive_key'     => $receiveKey,
                        'amount'          => (string) $amount,
                        'currency'        => strtoupper($currency),
                        'note'            => mb_substr($note, 0, 180),
                    ],
                );

            $json = $response->json();

            if ($response->status() === 429) {
                $retryAfter = $json['retry_after'] ?? 60;

                return [
                    'success'     => false,
                    'error'       => "تم تجاوز الحد. حاول بعد {$retryAfter} ثانية.",
                    'retry_after' => $retryAfter,
                    'rate_limit'  => true,
                ];
            }

            if (! $response->successful() || ! ($json['success'] ?? false)) {
                $errorMessage = $json['error']
                    ?? $json['message']
                    ?? $response->body()
                    ?? 'فشل غير معروف';

                return [
                    'success' => false,
                    'error'   => $errorMessage,
                    'raw'     => $json,
                ];
            }

            return $json;
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => 'فشل الاتصال: ' . $e->getMessage(),
            ];
        }
    }

    // ============================================================
    //  Helpers
    // ============================================================

    protected function get(array $query = []): ?array
    {
        try {
            $response = $this->client()->get($this->baseUrl, $query);

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('PaymentGateway error', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function client(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept'    => 'application/json',
            ])
            ->retry(2, 500);
    }

    protected function clientNoRetry(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept'    => 'application/json',
            ]);
    }
}
