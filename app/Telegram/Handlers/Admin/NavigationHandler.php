<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Keyboards\AdminsKeyboard\DashboardKeyboard;
use App\Telegram\Screens\Admin\DashboardScreen;
use SergiX44\Nutgram\Nutgram;

class NavigationHandler
{
    /**
     * خريطة العودة.
     */
    private const BACK_MAP = [
        'users'         => 'dashboard',
        'users.list'    => 'users',
        'users.search'  => 'users',
        'finance'       => 'dashboard',
        'referrals'     => 'dashboard',
        'system'        => 'dashboard',
        'communication' => 'dashboard',
        'support'       => 'dashboard',
        'analytics'     => 'dashboard',
    ];

    public function handle(Nutgram $bot, string $from): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        $destination = self::BACK_MAP[$from] ?? 'dashboard';

        match ($destination) {
            'dashboard' => $this->showDashboard($bot),
            default     => $this->showDashboard($bot),
        };
    }

    private function showDashboard(Nutgram $bot): void
    {
        $user = \App\Models\User::where('telegram_id', $bot->userId())->first();

        $this->safeEdit(
            $bot,
            DashboardScreen::text(),
            DashboardKeyboard::make($user?->isSuperAdmin() ?? false),
        );
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
