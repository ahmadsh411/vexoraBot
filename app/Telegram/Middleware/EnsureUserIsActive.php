<?php

namespace App\Telegram\Middleware;

use App\Models\User;
use SergiX44\Nutgram\Nutgram;

class EnsureUserIsActive
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $telegramUser = $bot->user();

        if ($telegramUser === null) {
            $next($bot);
            return;
        }

        $user = User::withTrashed()
            ->where('telegram_id', $telegramUser->id)
            ->first();

        if (! $user) {
            $next($bot);
            return;
        }

        if ($user->trashed()) {
            $this->reject($bot, '🗑️ حسابك محذوف. تواصل مع الإدارة.', true);
            return;
        }

        if (! $user->is_active) {
            $this->reject($bot, '🔴 حسابك معطّل. تواصل مع الإدارة.', false);
            return;
        }

        $next($bot);
    }

    private function reject(Nutgram $bot, string $text, bool $isDeleted): void
    {
        try {
            if ($bot->isCallbackQuery()) {
                $bot->answerCallbackQuery(text: $text, show_alert: true);
                return;
            }

            if ($isDeleted) {
                $bot->sendMessage(
                    text: implode("\n", [
                        '⚡ <b>VEXORA</b>',
                        '━━━━━━━━━━━━━━━━━━',
                        '',
                        '🗑️ <b>حسابك محذوف</b>',
                        '',
                        'لا يمكنك استخدام البوت.',
                        '',
                        'تواصل مع الإدارة.',
                    ]),
                    parse_mode: 'HTML',
                );
            } else {
                $bot->sendMessage(
                    text: implode("\n", [
                        '⚡ <b>VEXORA</b>',
                        '━━━━━━━━━━━━━━━━━━',
                        '',
                        '🔴 <b>الحساب غير مفعل</b>',
                        '',
                        'تواصل مع الإدارة.',
                    ]),
                    parse_mode: 'HTML',
                );
            }
        } catch (\Throwable $e) {
            // تجاهل
        }
    }
}
