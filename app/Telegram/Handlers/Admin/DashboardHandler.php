<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\User;
use App\Telegram\Keyboards\AdminsKeyboard\DashboardKeyboard;
use App\Telegram\Screens\Admin\DashboardScreen;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class DashboardHandler
{
    public function handle(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $user = $this->getCurrentUser($bot);

        if (! $user) {
            $bot->sendMessage(text: '❌ تعذر التعرف على حسابك.');
            return;
        }

        $this->safeEdit(
            $bot,
            DashboardScreen::text(),
            DashboardKeyboard::make($user->isSuperAdmin()),
        );
    }

    private function getCurrentUser(Nutgram $bot): ?User
    {
        return User::where('telegram_id', $bot->userId())->first();
    }

    private function safeAnswer(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
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
