<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\WithdrawService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class ProcessWithdrawJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        public readonly int $transactionId,
        public readonly int $adminId,
    ) {}

    public function handle(WithdrawService $withdrawService): void
    {
        $transaction = Transaction::find($this->transactionId);
        $admin = User::find($this->adminId);

        if (! $transaction || ! $admin) {
            Log::warning('ProcessWithdrawJob: missing data', [
                'transaction_id' => $this->transactionId,
                'admin_id'       => $this->adminId,
            ]);
            return;
        }

        if (! in_array($transaction->status, [
            Transaction::STATUS_PENDING,
            Transaction::STATUS_APPROVED,
        ], true)) {
            Log::info('ProcessWithdrawJob: already processed', [
                'transaction_id' => $transaction->id,
                'status'         => $transaction->status,
            ]);
            return;
        }

        Log::info('ProcessWithdrawJob: starting', [
            'transaction_id' => $transaction->id,
        ]);

        // ✅ استدعاء WithdrawService::approve()
        $result = $withdrawService->approve($transaction, $admin);

        if ($result['success']) {
            // ✅ إشعار المستخدم بالنجاح
            $this->notifyUserSuccess($transaction);

            // ✅ إشعار قناة Transactions بالنجاح
            $this->notifyChannelAboutSuccess($transaction, $admin);
        } else {
            // ✅ إشعار المستخدم بالفشل
            $this->notifyUserFailed($transaction, $result['error'] ?? 'خطأ غير معروف');

            // ✅ إشعار قناة Transactions بالفشل
            $this->notifyChannelAboutFailure($transaction, $admin, $result['error'] ?? 'خطأ غير معروف');
        }
    }

    // ============================================================
    //  إشعار النجاح — للمستخدم
    // ============================================================
    private function notifyUserSuccess(Transaction $transaction): void
    {
        $user = $transaction->user;

        if (! $user || ! $user->telegram_id) {
            return;
        }

        try {
            app(Nutgram::class)->sendMessage(
                text: implode("\n", [
                    '⚡ <b>VEXORA</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🎉 <b>تم تحويل السحب</b>',
                    '',
                    '💰 <b>المبلغ:</b> <b>'
                        . number_format((float) $transaction->amount_from, 2)
                        . ' ' . $transaction->from_currency . '</b>',
                    '',
                    '🎯 <b>الاستلام:</b>',
                    '└── <code>' . htmlspecialchars((string) $transaction->user_account_number, ENT_QUOTES, 'UTF-8') . '</code>',
                    '',
                    '📊 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
                    '',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '✅ تم الإرسال بنجاح.',
                ]),
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            Log::warning('ProcessWithdrawJob: notify success failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  إشعار الفشل — للمستخدم
    // ============================================================
    private function notifyUserFailed(Transaction $transaction, string $error): void
    {
        $user = $transaction->user;

        if (! $user || ! $user->telegram_id) {
            return;
        }

        try {
            app(Nutgram::class)->sendMessage(
                text: implode("\n", [
                    '⚡ <b>VEXORA</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '❌ <b>فشل إرسال السحب</b>',
                    '',
                    '💰 <b>المبلغ:</b> <b>'
                        . number_format((float) $transaction->amount_from, 2)
                        . ' ' . $transaction->from_currency . '</b>',
                    '',
                    '📊 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
                    '',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '📝 <b>السبب:</b>',
                    '<i>' . htmlspecialchars(\App\Helpers\ErrorMessages::friendly($error, 'withdraw'), ENT_QUOTES, 'UTF-8') . '</i>',
                    '',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '✅ <b>تم استرداد المبلغ إلى رصيدك.</b>',
                ]),
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            Log::warning('ProcessWithdrawJob: notify failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  📢 إشعار قناة Transactions — النجاح
    // ============================================================
    private function notifyChannelAboutSuccess(Transaction $transaction, User $admin): void
    {
        try {
            $user = $transaction->user;

            $text = implode("\n", [
                '✅ <b>تم تحويل سحب بنجاح</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🆔 <b>العملية:</b> <code>#' . $transaction->id . '</code>',
                '🔖 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
                '',
                '👤 <b>المستخدم:</b> <code>' . ($user?->username ?? '—') . '</code>',
                '💰 <b>المبلغ:</b> <b>' . number_format((float) $transaction->amount_from, 2) . ' ' . $transaction->from_currency . '</b>',
                '',
                '🎯 <b>الاستلام:</b> <code>' . htmlspecialchars((string) $transaction->user_account_number, ENT_QUOTES, 'UTF-8') . '</code>',
                '',
                '👮 <b>بواسطة:</b> <code>' . $admin->username . '</code>',
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            app(NotificationService::class)->notifyTransactionsChannel(
                app(Nutgram::class),
                $text,
            );
        } catch (\Throwable $e) {
            Log::warning('ProcessWithdrawJob: notify channel success failed', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  📢 إشعار قناة Transactions — الفشل
    // ============================================================
    private function notifyChannelAboutFailure(Transaction $transaction, User $admin, string $error): void
    {
        try {
            $user = $transaction->user;

            $text = implode("\n", [
                '❌ <b>فشل تحويل سحب</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🆔 <b>العملية:</b> <code>#' . $transaction->id . '</code>',
                '🔖 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
                '',
                '👤 <b>المستخدم:</b> <code>' . ($user?->username ?? '—') . '</code>',
                '💰 <b>المبلغ:</b> <b>' . number_format((float) $transaction->amount_from, 2) . ' ' . $transaction->from_currency . '</b>',
                '',
                '📝 <b>السبب:</b>',
                '<i>' . htmlspecialchars(\App\Helpers\ErrorMessages::friendly($error, 'withdraw'), ENT_QUOTES, 'UTF-8') . '</i>',
                '',
                '💸 <b>تم استرداد المبلغ للمستخدم.</b>',
                '',
                '👮 <b>بواسطة:</b> <code>' . $admin->username . '</code>',
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            app(NotificationService::class)->notifyTransactionsChannel(
                app(Nutgram::class),
                $text,
            );
        } catch (\Throwable $e) {
            Log::warning('ProcessWithdrawJob: notify channel failure failed', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
