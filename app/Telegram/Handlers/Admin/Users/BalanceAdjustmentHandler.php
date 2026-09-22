<?php

namespace App\Telegram\Handlers\Admin\Users;

use App\Models\User;
use App\Telegram\Conversations\Admin\Users\AdjustBalanceConversation;
use App\Telegram\Keyboards\AdminsKeyboard\Users\BalanceAdjustmentKeyboard;
use App\Telegram\Screens\Admin\Users\BalanceAdjustmentScreen;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class BalanceAdjustmentHandler
{
    // ============================================================
    //  📊 عرض
    // ============================================================

    public function show(Nutgram $bot, ?string $id = null): void
    {
        $this->safeAnswer($bot);

        $user = $this->resolveUser($bot, $id);

        if (! $user) {
            return;
        }

        $this->safeEdit(
            $bot,
            BalanceAdjustmentScreen::text($user),
            BalanceAdjustmentKeyboard::make($user->id),
        );
    }

    // ============================================================
    //  ➕ إضافة
    // ============================================================

    public function startAdd(Nutgram $bot, ?string $id = null): void
    {
        $this->startAdjustment($bot, $id, 'add');
    }

    // ============================================================
    //  ➖ خصم
    // ============================================================

    public function startSub(Nutgram $bot, ?string $id = null): void
    {
        $this->startAdjustment($bot, $id, 'sub');
    }

    // ============================================================
    //  📋 السجل
    // ============================================================

    public function history(Nutgram $bot, ?string $id = null): void
    {
        $this->safeAnswer($bot);

        $user = $this->resolveUser($bot, $id);

        if (! $user) {
            return;
        }

        $this->safeEdit(
            $bot,
            BalanceAdjustmentScreen::historyText($user),
            \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup::make()
                ->addRow(
                    \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                        text: '⬅️ رجوع',
                        callback_data: "admin.users.balance.{$user->id}",
                    ),
                ),
        );
    }

    // ============================================================
    //  Private
    // ============================================================

    private function startAdjustment(Nutgram $bot, ?string $id, string $action): void
    {
        $user = $this->resolveUser($bot, $id);

        if (! $user) {
            return;
        }

        $this->safeAnswer($bot);

        $cacheKey = 'adjust_balance_' . $bot->userId() . '_' . $bot->chatId();
        Cache::put($cacheKey, [
            'user_id' => $user->id,
            'action'  => $action,
        ], now()->addMinutes(10));

        AdjustBalanceConversation::begin(
            bot: $bot,
            userId: $bot->userId(),
            chatId: $bot->chatId(),
        );
    }

    private function resolveUser(Nutgram $bot, ?string $id): ?User
    {
        $id = $id ?: $this->extractId($bot);

        if (! $id) {
            $this->safeAlert($bot, '❌ معرّف غير صالح.');
            return null;
        }

        $user = User::withTrashed()->find((int) $id);

        if (! $user) {
            $this->safeAlert($bot, "❌ المستخدم #{$id} غير موجود.");
            return null;
        }

        if ($user->trashed()) {
            $this->safeAlert($bot, '⚠️ المستخدم محذوف. استعده أولاً.');
            return null;
        }

        return $user;
    }

    private function extractId(Nutgram $bot): ?int
    {
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
