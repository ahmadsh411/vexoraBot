<?php

namespace App\Telegram\Handlers\Admin\Users;

use App\Models\User;
use App\Telegram\Keyboards\AdminsKeyboard\Users\NewUsersKeyboard;
use App\Telegram\Screens\Admin\Users\NewUsersScreen;
use SergiX44\Nutgram\Nutgram;

class NewUsersHandler
{
    public function list(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        $this->safeEdit(
            $bot,
            NewUsersScreen::text(),
            NewUsersKeyboard::make(),
        );
    }

    public function markAllSeen(Nutgram $bot): void
    {
        $count = User::whereNull('admin_seen_at')->count();

        if ($count === 0) {
            try {
                $bot->answerCallbackQuery(
                    text: 'لا يوجد مستخدمون جدد.',
                    show_alert: true,
                );
            } catch (\Throwable $e) {
            }
            return;
        }

        User::whereNull('admin_seen_at')->update(['admin_seen_at' => now()]);

        try {
            $bot->answerCallbackQuery(
                text: "✅ تم الاطلاع على {$count} مستخدم.",
                show_alert: true,
            );
        } catch (\Throwable $e) {
        }

        $this->list($bot);
    }

    public static function markOneSeen(User $user): void
    {
        if ($user->admin_seen_at === null) {
            $user->update(['admin_seen_at' => now()]);
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
