<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\DepositService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Helpers\ErrorMessages;

class ProcessDepositJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $transactionId,
    ) {}

    public function handle(DepositService $depositService): void
    {
        $transaction = Transaction::find($this->transactionId);

        if (! $transaction) {
            Log::warning('ProcessDepositJob: transaction not found', [
                'transaction_id' => $this->transactionId,
            ]);
            return;
        }

        if (! $transaction->isPending()) {
            return;
        }

        try {
            $result = $depositService->processAfterCreate($transaction);

            Log::info('ProcessDepositJob: completed', [
                'transaction_id' => $transaction->id,
                'verified'       => $result['verified'] ?? false,
                'status'         => $result['status'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessDepositJob: failed', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
