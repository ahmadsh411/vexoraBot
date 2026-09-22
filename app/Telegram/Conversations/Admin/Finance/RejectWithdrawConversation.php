<?php

namespace App\Telegram\Conversations\Admin\Finance;

use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\WithdrawService;
use App\Telegram\Conversations\BaseConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class RejectWithdrawConversation extends BaseConversation
{
    protected ?int $transactionId = null;

    public function start(Nutgram $bot): void
    {
        $cacheKey = 'reject_withdraw_' . $bot->userId() . '_' . $bot->chatId();
        $payload = Cache::pull($cacheKey);

        if (! $payload || ! isset($payload['transaction_id'])) {
            $this->keep(
                $bot,
                '❌ انتهت الجلسة.',
                reply_markup: $this->backKeyboard(null),
            );
            $this->endAndClean($bot);
            return;
        }

        $this->transactionId = (int) $payload['transaction_id'];

        $transaction = Transaction::withdrawals()->find($this->transactionId);

        if (! $transaction || ! $transaction->isPending()) {
            $this->keep(
                $bot,
                '⚠️ الطلب غير متاح.',
                reply_markup: $this->backKeyboard(null),
            );
            $this->endAndClean($bot);
            return;
        }

        $user = $transaction->user;

        // 🗑️ سؤال — يُحذف
        $this->askTracked(
            $bot,
            implode("\n", [
                '❌ <b>رفض طلب السحب</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🆔 <b>العملية:</b> <code>#' . $transaction->id . '</code>',
                '👤 <b>المستخدم:</b> <code>' . ($user?->username ?? 'غير معروف') . '</code>',
                '💰 <b>المبلغ:</b> ' . number_format((float) $transaction->amount_from, 2) . ' ' . $transaction->from_currency,
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📝 <b>أرسل سبب الرفض</b>:',
                '',
                '↩️ أو /cancel للإلغاء.',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('handleReason');
    }

    public function handleReason(Nutgram $bot): void
    {
        $reason = trim((string) ($bot->message()->text ?? ''));

        if ($reason === '/cancel') {
            $this->keep(
                $bot,
                '❌ تم الإلغاء.',
                reply_markup: $this->backKeyboard($this->transactionId),
            );
            $this->endAndClean($bot);
            return;
        }

        if (mb_strlen($reason) < 3) {
            $this->askTracked($bot, '⚠️ السبب قصير جداً. أرسل سبباً أوضح:');
            return;
        }

        if (mb_strlen($reason) > 500) {
            $reason = mb_substr($reason, 0, 500);
        }

        $transaction = Transaction::withdrawals()->find($this->transactionId);

        if (! $transaction || ! $transaction->isPending()) {
            $this->keep(
                $bot,
                '⚠️ الطلب غير متاح.',
                reply_markup: $this->backKeyboard(null),
            );
            $this->endAndClean($bot);
            return;
        }

        $admin = User::where('telegram_id', $bot->userId())->first();

        if (! $admin) {
            $this->keep(
                $bot,
                '❌ تعذر التعرف على حسابك.',
                reply_markup: $this->backKeyboard(null),
            );
            $this->endAndClean($bot);
            return;
        }

        try {
            $result = app(WithdrawService::class)->reject($transaction, $admin, $reason);

            if (! $result['success']) {
                $this->keep(
                    $bot,
                    '❌ فشل: ' . ($result['error'] ?? 'خطأ'),
                    reply_markup: $this->backKeyboard($transaction->id),
                );
                $this->endAndClean($bot);
                return;
            }

            $transaction = $result['transaction'];

            // ✅ نجاح — يبقى + زر رجوع
            $this->keep(
                $bot,
                implode("\n", [
                    '✅ <b>تم رفض الطلب</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🆔 <b>العملية:</b> <code>#' . $transaction->id . '</code>',
                    '💰 <b>المبلغ:</b> ' . number_format((float) $transaction->amount_from, 2) . ' ' . $transaction->from_currency,
                    '',
                    '📝 <b>السبب:</b>',
                    '<i>' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</i>',
                    '',
                    '💳 <b>تم استرداد المبلغ للمستخدم.</b>',
                ]),
                parse_mode: 'HTML',
                reply_markup: $this->backKeyboard(null),
            );

            // ✅ إشعار المستخدم (chat_id مختلف — لا يُحذف)
            $this->notifyUser($bot, $transaction, $reason);

            // ✅ إشعار الأدمن — مع إصلاح BUG الأصلي
            try {
                app(NotificationService::class)->notifyAdmins(
                    $bot,
                    "❌ تم رفض السحب #{$transaction->id} بواسطة {$admin->username}\n📝 السبب: {$reason}",
                );
            } catch (\Throwable $e) {
                Log::warning('notifyAdmins failed', ['error' => $e->getMessage()]);
            }

            // ✅ إشعار القناة (اختياري)
            $this->notifyChannel($bot, $transaction, $reason, $admin);
        } catch (\Throwable $e) {
            Log::error('Reject withdraw failed', [
                'transaction_id' => $this->transactionId,
                'error'          => $e->getMessage(),
            ]);

            $this->keep(
                $bot,
                '❌ خطأ: ' . $e->getMessage(),
                reply_markup: $this->backKeyboard(null),
            );
        }

        $this->endAndClean($bot);
    }

    // ============================================================
    //  Notifications (لمستخدمين آخرين — لا تُلمس)
    // ============================================================

    private function notifyUser(Nutgram $bot, Transaction $transaction, string $reason): void
    {
        $user = $transaction->user;

        if (! $user || ! $user->telegram_id) return;

        try {
            $bot->sendMessage(
                text: implode("\n", [
                    '⚡ <b>VEXORA</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🔴 <b>تم رفض طلب السحب</b>',
                    '',
                    '💰 <b>المبلغ:</b> <b>' . number_format((float) $transaction->amount_from, 2) . ' ' . $transaction->from_currency . '</b>',
                    '',
                    '📝 <b>السبب:</b>',
                    '<i>' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</i>',
                    '',
                    '✅ <b>تم استرداد المبلغ إلى رصيدك.</b>',
                ]),
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
        }
    }

    private function notifyChannel(
        Nutgram $bot,
        Transaction $transaction,
        string $reason,
        User $admin,
    ): void {
        try {
            $user = $transaction->user;

            app(NotificationService::class)->notifyTransactionsChannel(
                $bot,
                implode("\n", [
                    '❌ <b>رفض طلب سحب</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🆔 <b>العملية:</b> <code>#' . $transaction->id . '</code>',
                    '👤 <b>المستخدم:</b> <code>' . htmlspecialchars($user?->username ?? 'غير معروف', ENT_QUOTES, 'UTF-8') . '</code>',
                    '',
                    '💰 <b>المبلغ:</b> <b>' . number_format((float) $transaction->amount_from, 2) . ' ' . $transaction->from_currency . '</b>',
                    '',
                    '📝 <b>السبب:</b>',
                    '<i>' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</i>',
                    '',
                    '👮 <b>بواسطة:</b> <code>' . $admin->username . '</code>',
                ]),
            );
        } catch (\Throwable $e) {
        }
    }

    /**
     * 🔙 زر الرجوع
     */
    private function backKeyboard(?int $transactionId): InlineKeyboardMarkup
    {
        $button = $transactionId
            ? InlineKeyboardButton::make(
                text: '⬅️ رجوع للطلب',
                callback_data: "admin.finance.withdraws.show.{$transactionId}",
            )
            : InlineKeyboardButton::make(
                text: '⬅️ رجوع للسحوبات',
                callback_data: 'admin.finance.withdraws.pending',
            );

        return InlineKeyboardMarkup::make()->addRow($button);
    }
}
