<?php

namespace App\Telegram\Conversations\Admin\Users;

use App\Models\User;
use App\Services\NotificationService;
use App\Services\TransactionService;
use App\Telegram\Conversations\BaseConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class AdjustBalanceConversation extends BaseConversation
{
    public function start(Nutgram $bot): void
    {
        $cacheKey = 'adjust_balance_' . $bot->userId() . '_' . $bot->chatId();
        $payload = Cache::pull($cacheKey);

        if (! $payload || ! isset($payload['user_id'], $payload['action'])) {
            $this->keep(
                $bot,
                '❌ انتهت صلاحية الجلسة.',
                reply_markup: $this->backKeyboard(null),
            );
            $this->endAndClean($bot);
            return;
        }

        $stateKey = $this->stateKey($bot);

        Cache::put($stateKey, [
            'user_id'  => (int) $payload['user_id'],
            'action'   => $payload['action'],
            'currency' => null,
            'amount'   => null,
            'reason'   => null,
        ], now()->addMinutes(30));

        $user = User::withTrashed()->find((int) $payload['user_id']);

        if (! $user || $user->trashed()) {
            $this->keep(
                $bot,
                '❌ المستخدم غير متاح.',
                reply_markup: $this->backKeyboard(null),
            );
            $this->endAndClean($bot);
            return;
        }

        $actionLabel = $payload['action'] === 'add' ? '➕ إضافة رصيد' : '➖ خصم رصيد';

        // 🗑️ سؤال — يُحذف
        $this->askTracked(
            $bot,
            implode("\n", [
                $actionLabel,
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👤 <b>المستخدم:</b> ' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8'),
                '🆔 <b>المعرف:</b> <code>' . $user->id . '</code>',
                '',
                'اختر العملة:',
            ]),
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '🇸🇾 NSP',
                        callback_data: 'adjust.currency.NSP',
                            style: ButtonStyle::SUCCESS,
                    ),
                    InlineKeyboardButton::make(
                        text: '💵 USD',
                        callback_data: 'adjust.currency.USD',
                            style: ButtonStyle::SUCCESS,
                    ),
                ),
        );

        $this->next('askCurrency');
    }

    public function askCurrency(Nutgram $bot): void
    {
        $callbackData = $bot->callbackQuery()?->data;

        if (! $callbackData || ! str_starts_with($callbackData, 'adjust.currency.')) {
            $this->askTracked($bot, '❌ اختر من الأزرار.');
            return;
        }

        $currency = strtoupper(substr($callbackData, strlen('adjust.currency.')));

        $state = $this->getState($bot);
        $state['currency'] = $currency;
        $this->putState($bot, $state);

        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        $actionLabel = $state['action'] === 'add' ? '➕ إضافة' : '➖ خصم';

        // 🗑️ سؤال — يُحذف
        $this->askTracked(
            $bot,
            implode("\n", [
                $actionLabel . ' <b>' . $currency . '</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📝 أرسل <b>المبلغ</b>:',
                '',
                '↩️ أو /cancel للإلغاء.',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('askAmount');
    }

    public function askAmount(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        $amount = (float) str_replace(',', '', $text);

        if ($amount <= 0) {
            $this->askTracked($bot, '❌ المبلغ يجب أن يكون رقماً موجباً.');
            return;
        }

        $state = $this->getState($bot);
        $state['amount'] = $amount;
        $this->putState($bot, $state);

        // 🗑️ سؤال — يُحذف
        $this->askTracked(
            $bot,
            implode("\n", [
                '📝 <b>سبب العملية</b>',
                '',
                'أرسل سبب التعديل (اختياري):',
                '',
                '↩️ أو /skip للتخطي.',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('askReason');
    }

    public function askReason(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text !== '' && ! str_starts_with($text, '/')) {
            $state = $this->getState($bot);
            $state['reason'] = $text;
            $this->putState($bot, $state);
        }

        $this->apply($bot);
    }

    private function apply(Nutgram $bot): void
    {
        $state = $this->getState($bot);

        $userId   = $state['user_id'] ?? null;
        $action   = $state['action'] ?? null;
        $currency = $state['currency'] ?? null;
        $amount   = $state['amount'] ?? null;
        $reason   = $state['reason'] ?? null;

        if (! $userId || ! $action || ! $currency || ! $amount) {
            $this->keep(
                $bot,
                '❌ بيانات ناقصة.',
                reply_markup: $this->backKeyboard(null),
            );
            $this->clearState($bot);
            $this->endAndClean($bot);
            return;
        }

        $user = User::withTrashed()->find((int) $userId);

        if (! $user || $user->trashed()) {
            $this->keep(
                $bot,
                '❌ المستخدم غير متاح.',
                reply_markup: $this->backKeyboard(null),
            );
            $this->clearState($bot);
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
            $this->clearState($bot);
            $this->endAndClean($bot);
            return;
        }

        try {
            $service = app(TransactionService::class);
            $isCredit = $action === 'add';

            $transaction = $service->adminAdjustment(
                user: $user,
                amount: $amount,
                currency: $currency,
                admin: $admin,
                isCredit: $isCredit,
                notes: $reason,
            );

            $service->approve($transaction, $admin);

            $user->refresh();
            $wallet = $user->wallet;

            $actionLabel = $isCredit ? 'إضافة' : 'خصم';

            // ✅ نجاح — يبقى + زر رجوع
            $this->keep(
                $bot,
                implode("\n", [
                    '✅ <b>تم ' . $actionLabel . ' الرصيد</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '👤 <b>المستخدم:</b> ' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8'),
                    '🆔 <b>المعرف:</b> <code>' . $user->id . '</code>',
                    '',
                    '📊 <b>العملية:</b>',
                    '   ' . ($isCredit ? '➕' : '➖') . ' <b>' . number_format($amount, 2) . ' ' . $currency . '</b>',
                    '',
                    '💰 <b>الرصيد الجديد:</b>',
                    '   🇸🇾 <b>' . number_format((float) $wallet->balance_nsp, 2) . '</b> NSP',
                    '   💵 <b>' . number_format((float) $wallet->balance_usd, 2) . '</b> USD',
                    '',
                    '📊 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
                ]),
                parse_mode: 'HTML',
                reply_markup: $this->backKeyboard($user->id),
            );

            // ✅ إشعار المستخدم (chat_id مختلف — لا يُحذف)
            $this->notifyUser($bot, $user, $transaction, $amount, $currency, $reason, $isCredit);

            // ✅ إشعار القناة (لا يُحذف)
            $this->notifyChannel($bot, $user, $transaction, $amount, $currency, $reason, $isCredit, $admin);
        } catch (\Throwable $e) {
            Log::error('Balance adjustment failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);

            $this->keep(
                $bot,
                '❌ خطأ: ' . $e->getMessage(),
                reply_markup: $this->backKeyboard($user->id),
            );
        }

        $this->clearState($bot);
        $this->endAndClean($bot);
    }

    // ============================================================
    //  Notifications (لمستخدمين آخرين — لا تُحذف)
    // ============================================================

    private function notifyUser(
        Nutgram $bot,
        User $user,
        $transaction,
        float $amount,
        string $currency,
        ?string $reason,
        bool $isCredit,
    ): void {
        if (! $user->telegram_id) return;

        try {
            $icon = $isCredit ? '➕' : '➖';
            $label = $isCredit ? 'تمت إضافة رصيد' : 'تم خصم رصيد';

            $bot->sendMessage(
                text: implode("\n", [
                    '⚡ <b>VEXORA</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    $icon . ' <b>' . $label . '</b>',
                    '',
                    '💰 <b>المبلغ:</b> <b>' . number_format($amount, 2) . ' ' . $currency . '</b>',
                    '',
                    '📝 <b>السبب:</b> ' . htmlspecialchars($reason ?? 'تعديل إداري', ENT_QUOTES, 'UTF-8'),
                    '',
                    '📊 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
                ]),
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
        }
    }

    private function notifyChannel(
        Nutgram $bot,
        User $user,
        $transaction,
        float $amount,
        string $currency,
        ?string $reason,
        bool $isCredit,
        User $admin,
    ): void {
        try {
            app(NotificationService::class)->notifyTransactionsChannel(
                $bot,
                implode("\n", [
                    ($isCredit ? '➕' : '➖') . ' <b>تعديل إداري على الرصيد</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '👤 <b>المستخدم:</b> ' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8'),
                    '🆔 <b>المعرف:</b> <code>' . $user->id . '</code>',
                    '',
                    '💰 <b>المبلغ:</b> <b>' . number_format($amount, 2) . ' ' . $currency . '</b>',
                    '📝 <b>السبب:</b> ' . htmlspecialchars($reason ?? '-', ENT_QUOTES, 'UTF-8'),
                    '',
                    '👮 <b>بواسطة:</b> <code>' . $admin->username . '</code>',
                ]),
            );
        } catch (\Throwable $e) {
        }
    }

    private function cancel(Nutgram $bot): void
    {
        $state = $this->getState($bot);
        $userId = $state['user_id'] ?? null;

        $this->keep(
            $bot,
            '❌ تم الإلغاء.',
            reply_markup: $this->backKeyboard($userId),
        );

        $this->clearState($bot);
        $this->endAndClean($bot);
    }

    /**
     * 🔙 زر الرجوع
     */
    private function backKeyboard(?int $userId): InlineKeyboardMarkup
    {
        $button = $userId
            ? InlineKeyboardButton::make(
                text: '⬅️ رجوع لتفاصيل المستخدم',
                callback_data: "admin.users.show.{$userId}",
            )
            : InlineKeyboardButton::make(
                text: '⬅️ رجوع للمستخدمين',
                callback_data: 'admin.users',
            );

        return InlineKeyboardMarkup::make()->addRow($button);
    }

    private function stateKey(Nutgram $bot): string
    {
        return 'adjust_balance_state_' . $bot->userId() . '_' . $bot->chatId();
    }

    private function getState(Nutgram $bot): array
    {
        return Cache::get($this->stateKey($bot), []);
    }

    private function putState(Nutgram $bot, array $state): void
    {
        Cache::put($this->stateKey($bot), $state, now()->addMinutes(30));
    }

    private function clearState(Nutgram $bot): void
    {
        Cache::forget($this->stateKey($bot));
    }
}
