<?php

namespace App\Telegram\Middleware;

use App\Models\Setting;
use App\Models\User;
use SergiX44\Nutgram\Nutgram;

class EnsureMaintenanceMode
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $isMaintenance = (bool) Setting::get('maintenance_mode', false);

        if (! $isMaintenance) {
            $next($bot);
            return;
        }

        $user = $this->getUser($bot);

        if ($user && $user->is_admin) {
            $next($bot);
            return;
        }

        $this->reject($bot);
    }

    private function getUser(Nutgram $bot): ?User
    {
        $telegramUser = $bot->user();

        if ($telegramUser === null) {
            return null;
        }

        return User::withTrashed()
            ->where('telegram_id', $telegramUser->id)
            ->first();
    }

    private function reject(Nutgram $bot): void
    {
        $message = Setting::get('maintenance_message', 'البوت تحت الصيانة، عد قريبًا');

        try {
            if ($bot->isCallbackQuery()) {
                $bot->answerCallbackQuery(
                    text: '🔧 البوت تحت الصيانة',
                    show_alert: true,
                );
                return;
            }

            $bot->sendMessage(
                text: implode("\n", [
                    '🔧 <b>البوت تحت الصيانة</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    $message,
                    '',
                    '🙏 نعتذر عن الإزعاج',
                ]),
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            // تجاهل
        }
    }
}
