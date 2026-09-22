<?php

namespace App\Telegram\Conversations\User;

use App\Models\User;
use App\Models\Wheel;
use App\Models\WheelUserState;
use App\Services\WheelService;
use App\Telegram\Conversations\BaseConversation;
use App\Telegram\Keyboards\User\UserWheelKeyboard;
use App\Telegram\Screens\User\UserWheelScreen;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class UserWheelConversation extends BaseConversation
{
    public function start(Nutgram $bot): void
    {
        $user = User::where('telegram_id', $bot->userId())->first();

        if (! $user) {
            $this->keep($bot, '❌ تعذر التعرف على حسابك.', reply_markup: $this->backKeyboard());
            $this->endAndClean($bot);
            return;
        }

        $wheelService = app(WheelService::class);
        $state = $wheelService->getState($user);

        if (! $state) {
            $this->keep(
                $bot,
                implode("\n", [
                    '🎡 <b>عجلة الحظ</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '⚠️ العجلة غير مفعّلة حالياً.',
                ]),
                parse_mode: 'HTML',
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        $wheel = $state['wheel'];

        $this->safeEditOrSend(
            $bot,
            text: UserWheelScreen::main($state),
            keyboard: UserWheelKeyboard::main($state, $wheel),
        );

        $this->next('handleAction');
    }

    public function handleAction(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;
        $this->safeAnswer($bot);

        if ($data === 'user.dashboard') {
            $this->endAndClean($bot);
            return;
        }

        if ($data === 'user.wheel.refresh') {
            $this->start($bot);
            return;
        }

        if ($data === 'user.wheel.history') {
            $this->showHistory($bot);
            return;
        }

        if ($data === 'user.wheel.spin') {
            $this->spin($bot);
            return;
        }
    }

    private function spin(Nutgram $bot): void
    {
        $user = User::where('telegram_id', $bot->userId())->first();

        if (! $user) {
            $this->endAndClean($bot);
            return;
        }

        try {
            $result = app(WheelService::class)->spin($user);
            $prize = $result['prize'];
            $spin  = $result['spin'];

            $this->safeAnswer($bot, '🎉 ' . $prize->name);

            $text = UserWheelScreen::spinResult($prize, $spin);

            // ✅ نتيجة اللفة — تبقى (لا تُسجّل، لأنها بلا end)
            $bot->sendMessage(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '🎡 العجلة مرة أخرى',
                            callback_data: 'user.wheel.refresh',
                            style: ButtonStyle::SUCCESS,
                        ),
                    )
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '🔙 لوحة التحكم',
                            callback_data: 'user.dashboard',
                        ),
                    ),
            );
        } catch (\RuntimeException $e) {
            $this->safeAlert($bot, $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Wheel spin failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            $this->safeAlert($bot, '❌ حدث خطأ، حاول لاحقاً.');
        }
    }

    private function showHistory(Nutgram $bot): void
    {
        $user = User::where('telegram_id', $bot->userId())->first();

        if (! $user) {
            $this->endAndClean($bot);
            return;
        }

        $spins = \App\Models\WheelSpin::forUser($user->id)
            ->latestFirst()
            ->limit(10)
            ->get();

        if ($spins->isEmpty()) {
            $this->safeAlert($bot, 'لا يوجد سجل لفات بعد.');
            return;
        }

        $this->safeEditOrSend(
            $bot,
            text: UserWheelScreen::history($spins),
            keyboard: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '↩️ رجوع للعجلة',
                        callback_data: 'user.wheel.refresh',
                        style: ButtonStyle::PRIMARY,
                    ),
                ),
        );

        $this->next('handleAction');
    }

    // ============================================================
    //  Helpers
    // ============================================================

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

    private function safeAnswer(Nutgram $bot, string $text = ''): void
    {
        try {
            $bot->answerCallbackQuery(
                text: $text,
                show_alert: $text !== '',
            );
        } catch (\Throwable $e) {
        }
    }

    private function safeAlert(Nutgram $bot, string $text): void
    {
        try {
            $bot->answerCallbackQuery(
                text: $text,
                show_alert: true,
            );
        } catch (\Throwable $e) {
        }
    }

    /**
     * 🔙 زر الرجوع الافتراضي
     */
    private function backKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع للوحة',
                    callback_data: 'user.dashboard',
                ),
            );
    }
}
