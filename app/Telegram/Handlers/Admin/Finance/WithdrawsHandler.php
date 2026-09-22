<?php

namespace App\Telegram\Handlers\Admin\Finance;

use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\WithdrawService;
use App\Telegram\Conversations\Admin\Finance\RejectWithdrawConversation;
use App\Telegram\Keyboards\AdminsKeyboard\Finance\WithdrawsKeyboard;
use App\Telegram\Screens\Admin\Finance\WithdrawsScreen;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class WithdrawsHandler
{
    public function __construct(
        private readonly WithdrawService $withdrawService,
    ) {}

    // ============================================================
    //  index
    // ============================================================

    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit($bot, WithdrawsScreen::text(), WithdrawsKeyboard::make());
    }

    public function pending(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit(
            $bot,
            WithdrawsScreen::listText('pending', '🟡 <b>طلبات السحب المعلقة</b>'),
            WithdrawsScreen::listKeyboard('pending'),
        );
    }

    public function approved(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit(
            $bot,
            WithdrawsScreen::listText('approved', '🟢 <b>طلبات السحب المعتمدة (اليوم)</b>'),
            WithdrawsScreen::listKeyboard('approved'),
        );
    }

    public function rejected(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit(
            $bot,
            WithdrawsScreen::listText('rejected', '🔴 <b>طلبات السحب المرفوضة (اليوم)</b>'),
            WithdrawsScreen::listKeyboard('rejected'),
        );
    }

    public function all(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit(
            $bot,
            WithdrawsScreen::listText('all', '📜 <b>كل طلبات السحب</b>'),
            WithdrawsScreen::listKeyboard('all'),
        );
    }

    // ============================================================
    //  show
    // ============================================================

    public function show(Nutgram $bot, ?string $id = null): void
    {
        $this->safeAnswer($bot);

        $id = $this->resolveId($id, $bot);

        if (! $id) {
            $this->safeAlert($bot, '❌ معرّف غير صالح.');
            return;
        }

        $transaction = Transaction::withdrawals()->with('user:id,username,telegram_id')->find($id);

        if (! $transaction) {
            $this->safeEdit($bot, '❌ الطلب غير موجود.', WithdrawsKeyboard::make());
            return;
        }

        $this->safeEdit(
            $bot,
            WithdrawsScreen::detailsText($transaction),
            WithdrawsScreen::detailsKeyboard($transaction),
        );
    }

    // ============================================================
    //  ✅ approve (مع إرسال)
    // ============================================================

    public function approve(Nutgram $bot, ?string $id = null): void
    {
        $id = $this->resolveId($id, $bot);

        if (! $id) {
            $this->safeAlert($bot, '❌ معرّف غير صالح.');
            return;
        }

        $transaction = Transaction::withdrawals()->find($id);

        if (! $transaction || ! $transaction->isPending()) {
            $this->safeAlert($bot, '⚠️ الطلب غير متاح.');
            return;
        }

        $admin = User::where('telegram_id', $bot->userId())->first();

        if (! $admin) {
            $this->safeAlert($bot, '❌ تعذر التعرف على حسابك.');
            return;
        }

        // ============================================================
        //  ✅ حساب التأخير العشوائي
        // ============================================================
        $minDelay = 30;      // 30 ثانية
        $maxDelay = 1800;    // 30 دقيقة
        $randomDelay = random_int($minDelay, $maxDelay);

        // ============================================================
        //  ✅ جدولة Job مع تأخير
        // ============================================================
        \App\Jobs\ProcessWithdrawJob::dispatch($transaction->id, $admin->id)
            ->delay(now()->addSeconds($randomDelay));

        // ============================================================
        //  ✅ تحديث حالة الطلب إلى "قيد المعالجة"
        // ============================================================
        $transaction->update([
            'status'   => Transaction::STATUS_APPROVED,   // ← Approved وليس Pending
            'admin_id' => $admin->id,
            'approved_at' => now(),
            'metadata' => array_merge($transaction->metadata ?? [], [
                'queued_for_processing' => true,
                'scheduled_at'          => now()->addSeconds($randomDelay)->toDateTimeString(),
                'delay_seconds'         => $randomDelay,
            ]),
        ]);

        // ============================================================
        //  ✅ إشعار الأدمن بالقبول
        // ============================================================
        $minutes = round($randomDelay / 60, 1);

        $this->safeAlert(
            $bot,
            "✅ تم قبول الطلب\n📤 سيُرسل خلال ~{$minutes} دقيقة",
        );

        // ============================================================
        //  ✅ إشعار القناة
        // ============================================================
        try {
            $user = $transaction->user;

            app(\App\Services\NotificationService::class)->notifyTransactionsChannel(
                $bot,
                implode("\n", [
                    '✅ <b>اعتماد سحب (قيد التنفيذ)</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🆔 <b>العملية:</b> <code>#' . $transaction->id . '</code>',
                    '👤 <b>المستخدم:</b> <code>' . htmlspecialchars($user?->username ?? 'غير معروف', ENT_QUOTES, 'UTF-8') . '</code>',
                    '',
                    '💰 <b>المبلغ:</b> <b>' . number_format((float) $transaction->amount_from, 2) . ' ' . $transaction->from_currency . '</b>',
                    '',
                    '⏱️ <b>الإرسال بعد:</b> ~' . $minutes . ' دقيقة',
                    '',
                    '👮 <b>بواسطة:</b> <code>' . $admin->username . '</code>',
                ]),
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel', [
                'error' => $e->getMessage(),
            ]);
        }

        // ============================================================
        //  ✅ إشعار المستخدم
        // ============================================================
        $this->notifyUserQueued($bot, $transaction, $minutes);

        // ============================================================
        //  ✅ إعادة عرض قائمة المعلقة
        // ============================================================
        $this->pending($bot);
    }

    // ============================================================
    //  📢 إشعار المستخدم بأن الطلب في قائمة الانتظار
    // ============================================================
    private function notifyUserQueued(Nutgram $bot, Transaction $transaction, float $minutes): void
    {
        $user = $transaction->user;

        if (! $user || ! $user->telegram_id) {
            return;
        }

        try {
            $bot->sendMessage(
                text: implode("\n", [
                    '⚡ <b>VEXORA</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🎉 <b>تم اعتماد طلب السحب</b>',
                    '',
                    '💰 <b>المبلغ:</b> <b>'
                        . number_format((float) $transaction->amount_from, 2)
                        . ' ' . $transaction->from_currency . '</b>',
                    '',
                    '📊 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
                    '',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '⏱️ <b>جاري الإرسال...</b>',
                    'سيصل خلال <b>~' . $minutes . ' دقيقة</b>',
                    '',
                    '🔔 <b>سيصلك إشعار عند الإرسال.</b>',
                ]),
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify user about queue', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  ❌ reject (يبدأ Conversation)
    // ============================================================

    public function reject(Nutgram $bot, ?string $id = null): void
    {
        $id = $this->resolveId($id, $bot);

        if (! $id) {
            $this->safeAlert($bot, '❌ معرّف غير صالح.');
            return;
        }

        $transaction = Transaction::withdrawals()->find($id);

        if (! $transaction || ! $transaction->isPending()) {
            $this->safeAlert($bot, '⚠️ الطلب غير متاح.');
            return;
        }

        $this->safeAnswer($bot);

        $cacheKey = 'reject_withdraw_' . $bot->userId() . '_' . $bot->chatId();
        Cache::put($cacheKey, ['transaction_id' => $transaction->id], now()->addMinutes(10));

        RejectWithdrawConversation::begin(
            bot: $bot,
            userId: $bot->userId(),
            chatId: $bot->chatId(),
        );
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function notifyUserApproved(Nutgram $bot, Transaction $transaction): void
    {
        $user = $transaction->user;

        if (! $user || ! $user->telegram_id) return;

        try {
            $bot->sendMessage(
                text: implode("\n", [
                    '⚡ <b>VEXORA</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🎉 <b>تم اعتماد سحبك</b>',
                    '',
                    '💰 <b>المبلغ:</b> <b>' . number_format((float) $transaction->amount_from, 2) . ' ' . $transaction->from_currency . '</b>',
                    '',
                    '📊 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
                    '',
                    '✅ تم تحويل المبلغ إلى حسابك.',
                ]),
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
        }
    }

    private function notifyUserFailed(Nutgram $bot, Transaction $transaction, string $error): void
    {
        $user = $transaction->user;

        if (! $user || ! $user->telegram_id) return;

        try {
            $bot->sendMessage(
                text: implode("\n", [
                    '⚡ <b>VEXORA</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '⚠️ <b>فشل إرسال السحب</b>',
                    '',
                    '💰 <b>المبلغ:</b> <b>' . number_format((float) $transaction->amount_from, 2) . ' ' . $transaction->from_currency . '</b>',
                    '',
                    '✅ تم استرداد المبلغ إلى رصيدك.',
                ]),
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
        }
    }

    private function notifyChannelApproved(
        Nutgram $bot,
        Transaction $transaction,
        User $admin,
    ): void {
        try {
            $user = $transaction->user;

            app(NotificationService::class)->notifyTransactionsChannel(
                $bot,
                implode("\n", [
                    '✅ <b>اعتماد سحب</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🆔 <b>العملية:</b> <code>#' . $transaction->id . '</code>',
                    '👤 <b>المستخدم:</b> <code>' . htmlspecialchars($user?->username ?? 'غير معروف', ENT_QUOTES, 'UTF-8') . '</code>',
                    '',
                    '💰 <b>المبلغ:</b> <b>' . number_format((float) $transaction->amount_from, 2) . ' ' . $transaction->from_currency . '</b>',
                    '',
                    '👮 <b>بواسطة:</b> <code>' . $admin->username . '</code>',
                ]),
            );
        } catch (\Throwable $e) {
        }
    }

    private function resolveId(?string $id, Nutgram $bot): ?int
    {
        if ($id !== null && is_numeric($id)) {
            return (int) $id;
        }

        $data = $bot->callbackQuery()?->data;

        if (! $data) {
            return null;
        }

        preg_match('/\.(\d+)(?:\.|$)/', $data, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }

    private function safeAnswer(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }
    }

    private function safeAlert(Nutgram $bot, string $text): void
    {
        try {
            $bot->answerCallbackQuery(text: $text, show_alert: true);
        } catch (\Throwable $e) {
        }
    }

    private function safeEdit(Nutgram $bot, string $text, $keyboard): void
    {
        try {
            $bot->editMessageText(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not modified')) {
                return;
            }

            $bot->sendMessage(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
            );
        }
    }
}
