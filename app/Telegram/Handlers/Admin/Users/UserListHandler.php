<?php

namespace App\Telegram\Handlers\Admin\Users;

use App\Models\User;
use App\Telegram\Conversations\Admin\Users\SearchUserConversation;
use App\Telegram\Keyboards\AdminsKeyboard\Users\ListUserKeyboard;
use App\Telegram\Screens\Admin\Users\ListUsersScreen;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class UserListHandler
{
    private const PER_PAGE = 10;

    // ============================================================
    //  📋 قائمة المستخدمين
    // ============================================================

    public function list(Nutgram $bot, ?string $page = null): void
    {
        $this->safeAnswer($bot);

        $page = max(1, (int) ($page ?? 1));
        $this->render($bot, $page);
    }

    // ============================================================
    //  🔍 البحث
    // ============================================================

    public function search(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        SearchUserConversation::begin(
            bot: $bot,
            userId: $bot->userId(),
            chatId: $bot->chatId(),
        );
    }

    // ============================================================
    //  Rendering
    // ============================================================

    private function render(Nutgram $bot, int $page): void
    {
        $total = User::count();
        $users = User::query()
            ->orderByDesc('created_at')
            ->forPage($page, self::PER_PAGE)
            ->get();

        $hasPrev = $page > 1;
        $hasNext = ($page * self::PER_PAGE) < $total;

        $this->safeEdit(
            $bot,
            ListUsersScreen::text($page),
            ListUserKeyboard::make(
                users: $users,
                currentPage: $page,
                hasPrev: $hasPrev,
                hasNext: $hasNext,
            ),
        );
    }

    // ============================================================
    //  Helpers
    // ============================================================

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

            Log::warning('UserListHandler failed', [
                'error' => $e->getMessage(),
            ]);

            $bot->sendMessage(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
            );
        }
    }
}
