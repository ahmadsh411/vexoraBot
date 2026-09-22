<?php

namespace App\Telegram\Middleware;

use App\Models\User;
use SergiX44\Nutgram\Nutgram;

class EnsureSuperAdmin
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $telegramUser = $bot->user();

        if ($telegramUser === null) {
            return;
        }

        $user = User::withTrashed()
            ->where('telegram_id', $telegramUser->id)
            ->first();

        if (! $user || $user->deleted_at !== null) {
            $this->reject($bot, '⛔ ليس لديك صلاحية.');
            return;
        }

        if (! $user->is_active) {
            $this->reject($bot, '🔴 حسابك معطّل.');
            return;
        }

        if (! $user->isSuperAdmin()) {
            $this->reject($bot, '👑 هذه الميزة للمشرف الأساسي فقط.');
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
