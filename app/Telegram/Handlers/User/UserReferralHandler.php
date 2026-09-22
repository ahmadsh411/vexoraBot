<?php

namespace App\Telegram\Handlers\User;

use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\User;
use App\Services\ReferralService;
use App\Telegram\Keyboards\User\UserReferralKeyboard;
use App\Telegram\Screens\User\UserReferralScreen;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;

class UserReferralHandler
{
    public function index(Nutgram $bot): void
    {
        $user = $this->getUser($bot);

        if (! $user) {
            return;
        }

        $this->safeAnswer($bot);

        // ✅ إذا لم يختر النوع بعد
        if (! $user->hasChosenReferralType()) {
            $bot->sendMessage(
                text: '⚠️ يجب اختيار نوع الإحالة أولاً.',
                reply_markup: \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup::make()
                    ->addRow(
                        \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                            text: '🎯 اختر النوع',
                            callback_data: 'user.choose-referral-type',
                            style: ButtonStyle::SUCCESS,
                        ),
                    ),
            );
            return;
        }

        $this->safeEdit(
            $bot,
            UserReferralScreen::main($user),
            UserReferralKeyboard::main(),
        );
    }

    // ============================================================
    //  👥 قائمة المُحالين
    // ============================================================

    public function listReferrals(Nutgram $bot): void
    {
        $user = $this->getUser($bot);

        if (! $user) {
            return;
        }

        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            UserReferralScreen::referralsList($user),
            UserReferralKeyboard::back(),
        );
    }

    // ============================================================
    //  🧾 سجل المكافآت
    // ============================================================

    public function rewards(Nutgram $bot, ?string $filter = null): void
    {
        $user = $this->getUser($bot);

        if (! $user) {
            return;
        }

        $this->safeAnswer($bot);

        if (! $filter) {
            $data = $bot->callbackQuery()?->data ?? '';
            $parts = explode('.', $data);
            $filter = $parts[3] ?? 'all';
        }

        $filter = in_array($filter, ['all', 'instant', 'cycle'], true)
            ? $filter
            : 'all';

        $this->safeEdit(
            $bot,
            UserReferralScreen::rewardsList($user, $filter),
            UserReferralKeyboard::rewards($filter),
        );
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function getUser(Nutgram $bot): ?User
    {
        return User::where('telegram_id', $bot->userId())->first();
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

    private function safeAnswer(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }
    }
}
