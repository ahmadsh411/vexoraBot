<?php

namespace App\Telegram\Middleware;

use App\Models\User;
use SergiX44\Nutgram\Nutgram;

class EnsureAdmin
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $telegramUser = $bot->user();

        if ($telegramUser === null) {
            $this->reject($bot, '❌ تعذر التحقق من هويتك.');
            return;
        }

        $user = User::withTrashed()
            ->where('telegram_id', $telegramUser->id)
            ->first();

        if (! $user) {
            $this->reject($bot, '⛔ ليس لديك صلاحية الوصول.');
            return;
        }

        if ($user->deleted_at !== null) {
            $this->reject($bot, '🗑️ حسابك محذوف.');
            return;
        }

        if (! $user->is_active) {
            $this->reject($bot, '🔴 حسابك معطّل.');
            return;
        }

        if (! $user->is_admin) {
            $this->reject($bot, '⛔ ليس لديك صلاحية الوصول.');
            return;
        }

        $next($bot);
    }

    private function reject(Nutgram $bot, string $text): void
    {
        try {
            if ($bot->isCallbackQuery()) {
                $bot->answerCallbackQuery(text: $text, show_alert: true);
            } else {
                $bot->sendMessage(text: $text);
            }
        } catch (\Throwable $e) {
        }
    }
}
