<?php

namespace App\Telegram\Conversations\User;

use App\Models\DepositMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DepositService;
use App\Telegram\Conversations\BaseConversation;
use App\Telegram\Keyboards\User\UserDepositKeyboard;
use App\Telegram\Screens\User\UserDepositScreen;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class UserDepositConversation extends BaseConversation
{
    protected ?int $methodId = null;
    protected ?float $amount = null;
    protected ?string $transactionId = null;

    // ============================================================
    //  🚀 البداية
    // ============================================================

    public function start(Nutgram $bot): void
    {
        $methods = DepositMethod::active()->ordered()->get();

        if ($methods->isEmpty()) {
            $this->keep(
                $bot,
                implode("\n", [
                    '💰 <b>شحن المحفظة</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '⚠️ <b>لا توجد طرق إيداع متاحة حالياً</b>',
                ]),
                parse_mode: 'HTML',
                reply_markup: UserDepositKeyboard::back(),
            );
            $this->endAndClean($bot);
            return;
        }

        try {
            $bot->editMessageText(
                text: UserDepositScreen::chooseMethod(),
                parse_mode: 'HTML',
                reply_markup: UserDepositKeyboard::methods($methods),
            );
        } catch (\Throwable $th) {
            Log::warning('editMessageText failed: ' . $th->getMessage());
        }

        $this->next('handleMethodChoice');
    }

    // ============================================================
    //  💳 اختيار الطريقة
    // ============================================================

    public function handleMethodChoice(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;
        $this->safeAnswer($bot);

        if ($data === 'user.deposit.cancel') {
            $this->cancel($bot);
            return;
        }

        if ($data === 'user.dashboard') {
            $this->endAndClean($bot);
            return;
        }

        if (! str_starts_with((string) $data, 'user.deposit.method.')) {
            $this->start($bot);
            return;
        }

        $methodId = (int) substr($data, strlen('user.deposit.method.'));
        $method = DepositMethod::find($methodId);

        if (! $method || ! $method->is_active) {
            $this->start($bot);
            return;
        }

        $this->methodId = $method->id;

        // 🗑️ سؤال — يُحذف
        $this->askTracked(
            $bot,
            UserDepositScreen::askAmount($method),
            parse_mode: 'HTML',
            reply_markup: UserDepositKeyboard::backToMethods(),
        );

        $this->next('handleAmount');
    }

    // ============================================================
    //  💵 استقبال المبلغ
    // ============================================================

    public function handleAmount(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));
        $method = DepositMethod::find($this->methodId);

        if (! $method) {
            $this->start($bot);
            return;
        }

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if (! is_numeric($text) || (float) $text <= 0) {
            $this->askTracked($bot, '⚠️ الرجاء إرسال مبلغ صحيح.');
            return;
        }

        $amount = (float) $text;

        $validator = app(\App\Services\DepositValidationService::class);
        $result = $validator->validate($method, $amount);

        if (! $result['valid']) {
            $this->askTracked($bot, $result['error'], parse_mode: 'HTML');
            return;
        }

        $this->amount = $amount;

        // 🗑️ سؤال — يُحذف
        $this->askTracked(
            $bot,
            UserDepositScreen::askTransactionId($method, $amount),
            parse_mode: 'HTML',
            reply_markup: UserDepositKeyboard::backToMethods(),
        );

        $this->next('handleTransactionId');
    }

    // ============================================================
    //  🔢 استقبال رقم العملية
    // ============================================================

    public function handleTransactionId(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if (mb_strlen($text) < 4) {
            $this->askTracked($bot, '⚠️ رقم العملية قصير جدًا. حاول مرة أخرى:');
            return;
        }

        $this->transactionId = $text;

        $exists = Transaction::where('ichancy_transaction_id', $text)->exists();

        if ($exists) {
            $this->askTracked(
                $bot,
                '⚠️ <b>رقم العملية مستخدم مسبقًا</b>' . "\n\n" .
                    'تحقق من الرقم أو تواصل مع الدعم.',
                parse_mode: 'HTML',
            );
            return;
        }

        $this->showConfirmation($bot);
    }

    // ============================================================
    //  ✅ عرض التأكيد
    // ============================================================

    private function showConfirmation(Nutgram $bot): void
    {
        $method = DepositMethod::find($this->methodId);

        // 🗑️ تأكيد وسيط — يُحذف
        $this->askTracked(
            $bot,
            UserDepositScreen::confirm(
                $method,
                $this->amount,
                $this->transactionId,
                false,
            ),
            parse_mode: 'HTML',
            reply_markup: UserDepositKeyboard::confirm(),
        );

        $this->next('handleFinalConfirm');
    }

    // ============================================================
    //  ✅ التأكيد النهائي
    // ============================================================

    public function handleFinalConfirm(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;
        $this->safeAnswer($bot);

        if ($data === 'user.deposit.cancel') {
            $this->cancel($bot);
            return;
        }

        if ($data !== 'user.deposit.confirm') {
            return;
        }

        $user = User::where('telegram_id', $bot->userId())->first();
        $method = DepositMethod::find($this->methodId);

        if (! $user || ! $method || ! $this->amount || ! $this->transactionId) {
            $this->cancel($bot);
            return;
        }

        try {
            // ⏳ رسالة تحميل 1
            try {
                $bot->editMessageText(
                    text: implode("\n", [
                        '',
                        '⏳ <b>جاري إنشاء طلبك...</b>',
                        '━━━━━━━━━━━━━━━━━━',
                        '',
                        '📤 يتم إرسال البيانات',
                        '⚡ الرجاء الانتظار...',
                        '',
                    ]),
                    parse_mode: 'HTML',
                    reply_markup: UserDepositKeyboard::back(),
                );
            } catch (\Throwable $e) {
            }

            $result = app(DepositService::class)->createRequest(
                user: $user,
                method: $method,
                amount: $this->amount,
                transactionId: $this->transactionId,
                proofFile: null,
            );

            if (! $result['success']) {
                $this->keep(
                    $bot,
                    '❌ <b>فشل الإنشاء</b>' . "\n\n" . ($result['error'] ?? ''),
                    parse_mode: 'HTML',
                    reply_markup: UserDepositKeyboard::back(),
                );
                $this->endAndClean($bot);
                return;
            }

            $transaction = $result['transaction'];

            // ⏳ رسالة تحميل 2
            try {
                $bot->editMessageText(
                    text: implode("\n", [
                        '',
                        '⏳ <b>جاري التحقق من الدفع...</b>',
                        '━━━━━━━━━━━━━━━━━━',
                        '',
                        '🔄 يتم التحقق من العملية',
                        '💰 <b>المبلغ:</b> ' . number_format($this->amount, 2) . ' ' . $method->currency,
                        '🔖 <b>المرجع:</b> <code>' . $this->transactionId . '</code>',
                        '',
                        '⚡ <i>الرجاء الانتظار...</i>',
                        '',
                    ]),
                    parse_mode: 'HTML',
                    reply_markup: UserDepositKeyboard::back(),
                );
            } catch (\Throwable $e) {
            }

            try {
                app(DepositService::class)->processAfterCreate($transaction);
            } catch (\Throwable $e) {
                Log::warning('Auto-verification failed', [
                    'transaction_id' => $transaction->id,
                    'error'          => $e->getMessage(),
                ]);
            }

            $transaction->refresh();

            // ✅ نتيجة نهائية — تبقى
            if ($transaction->isCompleted()) {
                $this->keep(
                    $bot,
                    UserDepositScreen::successAuto($transaction, $method),
                    parse_mode: 'HTML',
                    reply_markup: UserDepositKeyboard::success(),
                );
            } else {
                $this->keep(
                    $bot,
                    UserDepositScreen::success($transaction, $method),
                    parse_mode: 'HTML',
                    reply_markup: UserDepositKeyboard::success(),
                );
            }

            // 📢 إشعار القناة (لا يُلمس)
            try {
                app(\App\Services\NotificationService::class)->notifyTransactionsChannel(
                    $bot,
                    UserDepositScreen::adminNotification($transaction, $user, $method),
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to notify channel', [
                    'transaction_id' => $transaction->id,
                    'error'          => $e->getMessage(),
                ]);
            }

            // 🔔 إشعار الأدمن مباشرة
            if (! $transaction->isCompleted()) {
                try {
                    $this->notifyAdminsDirectly($bot, $transaction, $user, $method);
                } catch (\Throwable $e) {
                    Log::warning('Failed to notify admins directly', [
                        'transaction_id' => $transaction->id,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Deposit creation failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            $this->keep(
                $bot,
                '❌ <b>فشل الإنشاء</b>' . "\n\n" . $e->getMessage(),
                parse_mode: 'HTML',
                reply_markup: UserDepositKeyboard::back(),
            );
        }

        $this->endAndClean($bot);
    }

    // ============================================================
    //  📢 إشعار الأدمن مباشرة (لأشخاص آخرين — لا يُحذف)
    // ============================================================

    private function notifyAdminsDirectly(
        Nutgram $bot,
        Transaction $transaction,
        User $user,
        DepositMethod $method,
    ): void {
        $admins = User::where('is_admin', true)
            ->where('is_active', true)
            ->whereNotNull('telegram_id')
            ->get();

        if ($admins->isEmpty()) {
            Log::warning('No active admins to notify', [
                'transaction_id' => $transaction->id,
            ]);
            return;
        }

        $text = UserDepositScreen::adminNotification($transaction, $user, $method);
        $keyboard = UserDepositKeyboard::adminActions($transaction);

        $sent = 0;
        $failed = 0;

        foreach ($admins as $admin) {
            try {
                $bot->sendMessage(
                    text: $text,
                    chat_id: $admin->telegram_id,
                    parse_mode: 'HTML',
                    reply_markup: $keyboard,
                );
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Failed to send direct admin notification', [
                    'admin_id'       => $admin->id,
                    'transaction_id' => $transaction->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        Log::info('Admin notifications sent', [
            'transaction_id' => $transaction->id,
            'sent'           => $sent,
            'failed'         => $failed,
        ]);
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function cancel(Nutgram $bot): void
    {
        $this->keep($bot, '❌ تم إلغاء عملية الإيداع.');
        $this->endAndClean($bot);
    }

    private function safeAnswer(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }
    }
}
