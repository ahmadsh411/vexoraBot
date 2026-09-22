<?php

namespace App\Telegram\Conversations\User;

use App\Models\DepositMethod;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserPaymentAccount;
use App\Services\UserPaymentAccountService;
use App\Services\WithdrawFeeService;
use App\Services\WithdrawNotificationService;
use App\Services\WithdrawService;
use App\Telegram\Conversations\BaseConversation;
use App\Telegram\Keyboards\User\UserWithdrawKeyboard;
use App\Telegram\Screens\User\UserWithdrawScreen;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class UserWithdrawConversation extends BaseConversation
{
    protected ?int $methodId = null;
    protected ?float $amount = null;
    protected ?string $destination = null;
    protected ?string $destinationName = null;
    protected bool $isNewAccount = false;

    // ============================================================
    //  🚀 البداية
    // ============================================================

    public function start(Nutgram $bot): void
    {
        if (! (bool) Setting::get('withdraws_enabled', true)) {
            $this->keep(
                $bot,
                implode("\n", [
                    '📤 <b>السحب من المحفظة</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🔴 <b>خدمة السحب معطّلة مؤقتاً</b>',
                    '',
                    '⏳ حاول لاحقاً.',
                ]),
                parse_mode: 'HTML',
                reply_markup: UserWithdrawKeyboard::back(),
            );
            $this->endAndClean($bot);
            return;
        }

        $methods = DepositMethod::active()->ordered()->get();

        if ($methods->isEmpty()) {
            $this->keep(
                $bot,
                implode("\n", [
                    '📤 <b>السحب من المحفظة</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '⚠️ <b>لا توجد طرق سحب متاحة حالياً</b>',
                ]),
                parse_mode: 'HTML',
                reply_markup: UserWithdrawKeyboard::back(),
            );
            $this->endAndClean($bot);
            return;
        }

        $user = User::where('telegram_id', $bot->userId())->first();

        if (! $user) {
            $this->keep($bot, '❌ تعذر التعرف على حسابك.', reply_markup: UserWithdrawKeyboard::back());
            $this->endAndClean($bot);
            return;
        }

        // ✅ يُحدّث نفس الرسالة (start)
        $this->safeEditOrSend(
            $bot,
            text: UserWithdrawScreen::chooseMethod($user),
            keyboard: UserWithdrawKeyboard::methods($methods),
        );

        $this->next('handleMethodChoice');
    }

    // ============================================================
    //  💳 اختيار الطريقة/الحساب
    // ============================================================

    public function handleMethodChoice(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;
        $this->safeAnswer($bot);

        if ($data === 'user.withdraw.cancel') {
            $this->cancel($bot);
            return;
        }

        if ($data === 'user.dashboard') {
            $this->endAndClean($bot);
            return;
        }

        if ($data === 'user.withdraw') {
            $this->start($bot);
            return;
        }

        if ($data === 'user.withdraw.new-account') {
            $method = DepositMethod::find($this->methodId);

            if (! $method) {
                $this->start($bot);
                return;
            }

            $this->isNewAccount = true;
            $this->askNewAccount($bot, $method);
            return;
        }

        if (str_starts_with((string) $data, 'user.withdraw.account.')) {
            $method = DepositMethod::find($this->methodId);

            if (! $method) {
                $this->start($bot);
                return;
            }

            $accountId = (int) substr($data, strlen('user.withdraw.account.'));
            $user = User::where('telegram_id', $bot->userId())->first();

            if (! $user) {
                $this->start($bot);
                return;
            }

            $account = UserPaymentAccount::where('user_id', $user->id)
                ->where('id', $accountId)
                ->first();

            if (! $account) {
                $this->start($bot);
                return;
            }

            $this->destination = $account->account_number;
            $this->destinationName = $account->account_name;
            $this->isNewAccount = false;

            $this->askAmount($bot, $method);
            return;
        }

        if (! str_starts_with((string) $data, 'user.withdraw.method.')) {
            $this->start($bot);
            return;
        }

        $methodId = (int) substr($data, strlen('user.withdraw.method.'));
        $method = DepositMethod::find($methodId);

        if (! $method || ! $method->is_active) {
            $this->start($bot);
            return;
        }

        $this->methodId = $method->id;

        $user = User::where('telegram_id', $bot->userId())->first();

        $accounts = app(UserPaymentAccountService::class)
            ->getUserAccounts($user)
            ->where('deposit_method_id', $method->id);

        if ($accounts->isNotEmpty()) {
            $this->safeEditOrSend(
                $bot,
                text: UserWithdrawScreen::chooseAccount($method, $accounts),
                keyboard: UserWithdrawKeyboard::accounts($method, $accounts),
            );

            $this->next('handleMethodChoice');
            return;
        }

        $this->isNewAccount = true;
        $this->askNewAccount($bot, $method);
    }

    // ============================================================
    //  📝 طلب حساب جديد
    // ============================================================

    private function askNewAccount(Nutgram $bot, DepositMethod $method): void
    {
        // 🗑️ سؤال — يُحذف
        $this->askTracked(
            $bot,
            UserWithdrawScreen::askDestination($method),
            parse_mode: 'HTML',
            reply_markup: UserWithdrawKeyboard::backToMethods(),
        );

        $this->next('handleDestination');
    }

    // ============================================================
    //  🎯 استقبال الوجهة
    // ============================================================

    public function handleDestination(Nutgram $bot): void
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

        if ($method->isSyriatel()) {
            if (! preg_match('/^09\d{8}$/', $text)) {
                $this->askTracked(
                    $bot,
                    implode("\n", [
                        '❌ <b>رقم GSM غير صالح</b>',
                        '',
                        'يجب أن يبدأ بـ <code>09</code> ويتكون من 10 أرقام.',
                        'مثال: <code>0933000000</code>',
                    ]),
                    parse_mode: 'HTML',
                );
                return;
            }
        } elseif ($method->isShamCash()) {
            if (! preg_match('/^[a-f0-9]{32}$/i', $text)) {
                $this->askTracked(
                    $bot,
                    implode("\n", [
                        '❌ <b>عنوان غير صالح</b>',
                        '',
                        'يجب أن يكون 32 حرف hex.',
                        'مثال:',
                        '<code>06ff99d12f3b34d7956e3caaf756873e</code>',
                    ]),
                    parse_mode: 'HTML',
                );
                return;
            }
        }

        $this->destination = $text;

        // 🗑️ سؤال — يُحذف
        $this->askTracked(
            $bot,
            UserWithdrawScreen::askDestinationName(),
            parse_mode: 'HTML',
        );

        $this->next('handleDestinationName');
    }

    // ============================================================
    //  👤 اسم صاحب الحساب
    // ============================================================

    public function handleDestinationName(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));
        $method = DepositMethod::find($this->methodId);

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if ($text === '/skip' || $text === '-') {
            $this->destinationName = null;
        } else {
            $this->destinationName = $text;
        }

        $this->askAmount($bot, $method);
    }

    // ============================================================
    //  💵 طلب المبلغ
    // ============================================================

    private function askAmount(Nutgram $bot, DepositMethod $method): void
    {
        $user = User::where('telegram_id', $bot->userId())->first();

        // 🗑️ سؤال — يُحذف
        $this->askTracked(
            $bot,
            UserWithdrawScreen::askAmount($user, $method),
            parse_mode: 'HTML',
            reply_markup: UserWithdrawKeyboard::backToMethods(),
        );

        $this->next('handleAmount');
    }

    // ============================================================
    //  💰 استقبال المبلغ
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

        $this->amount = (float) $text;

        $user = User::where('telegram_id', $bot->userId())->first();
        $feeService = app(WithdrawFeeService::class);
        $feeData = $feeService->calculate($this->amount);

        // ✅ يُحدّث نفس الرسالة (تأكيد)
        $this->safeEditOrSend(
            $bot,
            text: UserWithdrawScreen::confirm(
                $user,
                $method,
                $this->amount,
                $feeData['fee'],
                $feeData['total'],
                $feeData['percent'],
                $this->destination,
                $this->destinationName,
            ),
            keyboard: UserWithdrawKeyboard::confirm(),
        );

        $this->next('handleConfirm');
    }

    // ============================================================
    //  ✅ التأكيد النهائي
    // ============================================================

    public function handleConfirm(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;
        $this->safeAnswer($bot);

        if ($data === 'user.withdraw.cancel') {
            $this->cancel($bot);
            return;
        }

        if ($data !== 'user.withdraw.confirm') {
            return;
        }

        $user = User::where('telegram_id', $bot->userId())->first();
        $method = DepositMethod::find($this->methodId);

        if (! $user || ! $method || ! $this->amount || ! $this->destination) {
            $this->cancel($bot);
            return;
        }

        $result = app(WithdrawService::class)->createRequest(
            user: $user,
            method: $method,
            amount: $this->amount,
            destination: $this->destination,
            destinationName: $this->destinationName,
        );

        if (! $result['success']) {
            $this->keep(
                $bot,
                $result['error'] ?? '❌ فشل إنشاء الطلب.',
                reply_markup: UserWithdrawKeyboard::back(),
            );
            $this->endAndClean($bot);
            return;
        }

        $transaction = $result['transaction'];

        // ✅ نجاح — يبقى
        $this->keep(
            $bot,
            UserWithdrawScreen::success($transaction, $method),
            parse_mode: 'HTML',
            reply_markup: UserWithdrawKeyboard::success(),
        );

        // ✅ إشعار السوبر أدمن (chat_id مختلف — لا يُحذف)
        try {
            app(WithdrawNotificationService::class)
                ->notifySuperAdmins($bot, $transaction, $user, $method);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify super admins', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }

        $this->endAndClean($bot);
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function cancel(Nutgram $bot): void
    {
        // ✅ إلغاء — يبقى + زر رجوع
        $this->keep(
            $bot,
            '❌ تم إلغاء عملية السحب.',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '↩️ رجوع للوحة',
                        callback_data: 'user.dashboard',
                    ),
                ),
        );
        $this->endAndClean($bot);
    }

    /**
     * ✏️ يُحدّث الرسالة الحالية (لا تراكم)
     */
    private function safeEditOrSend(Nutgram $bot, string $text, $keyboard = null): void
    {
        try {
            $bot->editMessageText(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
            );
        } catch (\Throwable $e) {
            try {
                $bot->sendMessage(
                    text: $text,
                    parse_mode: 'HTML',
                    reply_markup: $keyboard,
                );
            } catch (\Throwable $e2) {
                Log::warning('safeEditOrSend failed', ['error' => $e2->getMessage()]);
            }
        }
    }

    private function safeAnswer(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }
    }
}
