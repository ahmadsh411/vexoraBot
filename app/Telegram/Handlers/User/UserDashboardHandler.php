<?php

namespace App\Telegram\Handlers\User;

use App\Models\Transaction;
use App\Models\User;
use App\Telegram\Conversations\ChooseReferralTypeConversation;
use App\Telegram\Conversations\User\UserDepositConversation;
use App\Telegram\Conversations\User\UserWithdrawConversation;

use App\Telegram\Keyboards\User\UserDashboardKeyboard;
use App\Telegram\Keyboards\User\UserProfileKeyboard;
use App\Telegram\Keyboards\User\UserTransactionsKeyboard;
use App\Telegram\Screens\User\UserDashboardScreen;
use App\Telegram\Screens\User\UserProfileScreen;
use App\Telegram\Screens\User\UserTransactionsScreen;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;

class UserDashboardHandler
{
    // ============================================================
    //  🏠 Dashboard
    // ============================================================

    public function index(Nutgram $bot): void
    {
        $user = $this->getUser($bot);

        if (! $user) {
            $bot->sendMessage(text: '❌ تعذر التعرف على حسابك.');
            return;
        }

        // ✅ أول مرة — يختار نوع الإحالة
        if (! $user->hasChosenReferralType() && $user->referral_code) {
            $bot->sendMessage(
                text: UserDashboardScreen::text($user),
                reply_markup: UserDashboardKeyboard::main(),
                parse_mode: 'HTML',
            );

            // ✅ عرض رسالة منفصلة لاختيار النوع
            $bot->sendMessage(
                text: '🎯 <b>مطلوب: اختر نوع الإحالة</b>',
                parse_mode: 'HTML',
                reply_markup: \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup::make()
                    ->addRow(
                        \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                            text: '🎯 اختر النوع الآن',
                            callback_data: 'user.choose-referral-type',
                            style: ButtonStyle::SUCCESS,
                        ),
                    ),
            );

            return;
        }

        $this->safeEditOrSend(
            $bot,
            UserDashboardScreen::text($user),
            UserDashboardKeyboard::main(),
        );


    }

    // ============================================================
    //  👤 Profile
    // ============================================================

    public function profile(Nutgram $bot): void
    {
        $user = $this->getUser($bot);

        if (! $user) {
            return;
        }

        // ✅ 1. إغلاق الـ callback
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        // ✅ 2. رسالة الانتظار (نعدّل الرسالة الحالية)
        $waitMessageId = null;

        try {
            $bot->editMessageText(
                text: implode("\n", [
                    '⏳ <b>جاري جلب رصيدك...</b>',
                    '',
                    '🎮 يتم الاتصال بنظام اللعب',
                ]),
                parse_mode: 'HTML',
                disable_web_page_preview: true,
            );
        } catch (\Throwable $e) {
            // يمكن الرسالة قديمة → نرسل واحدة جديدة
            try {
                $waitMsg = $bot->sendMessage(
                    text: '⏳ <b>جاري جلب رصيدك...</b>',
                    parse_mode: 'HTML',
                );
                $waitMessageId = $waitMsg?->message_id;
            } catch (\Throwable $e2) {
            }
        }

        // ✅ 3. جلب رصيد IChancy
        $ichancyBalance  = null;
        $ichancyCurrency = null;

        if ($user->ichancyAccount) {
            try {
                $balanceData = app(\App\Services\IChancy\IChancyAccountService::class)
                    ->getBalanceForUser($user, 60);

                if ($balanceData) {
                    $ichancyBalance  = $balanceData['balance'];
                    $ichancyCurrency = $balanceData['currency'];
                }
            } catch (\Throwable $e) {
                Log::warning('Profile: IChancy balance failed', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // ✅ 4. حذف رسالة الانتظار (إذا كانت رسالة منفصلة)
        if ($waitMessageId) {
            try {
                $bot->deleteMessage(
                    chat_id: $bot->chatId(),
                    message_id: $waitMessageId,
                );
            } catch (\Throwable $e) {
            }
        }

        // ✅ 5. عرض الملف الشخصي النهائي
        $this->safeEditOrSend(
            $bot,
            UserProfileScreen::text($user, $ichancyBalance, $ichancyCurrency),
            UserProfileKeyboard::make(),
        );
    }

    // ============================================================
    //  💰 Deposit (يبدأ Conversation)
    // ============================================================

    public function deposit(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        UserDepositConversation::begin($bot);
    }

    // ============================================================
    //  📤 Withdraw
    // ============================================================

    public function withdraw(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        UserWithdrawConversation::begin($bot);
    }

    // ============================================================
    //  🎯 Choose Referral Type
    // ============================================================

    public function chooseReferralType(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        ChooseReferralTypeConversation::begin($bot);
    }

    // ============================================================
    //  📜 Transactions
    // ============================================================

    public function transactions(Nutgram $bot): void
    {
        $user = $this->getUser($bot);

        if (! $user) {
            return;
        }

        $this->safeEditOrSend(
            $bot,
            UserTransactionsScreen::main($user),
            UserTransactionsKeyboard::main(),
        );
    }


    // ============================================================
    //  ⚙️ الإعدادات
    // ============================================================
    public function settings(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        $this->safeEditOrSend(
            $bot,
            implode("
", [
                "",
                "⚙️ <b>إعدادات الحساب</b>",
                "━━━━━━━━━━━━━━━━━━",
                "",
                "من هنا يمكنك إدارة حسابك",
                "",
                "⚠️ <i>الحذف نهائي ولا يمكن التراجع</i>",
                "",
            ]),
            \App\Telegram\Keyboards\User\UserDashboardKeyboard::settings(),
        );
    }

    // ============================================================
    //  🗑 حذف الحساب
    // ============================================================
    public function deleteAccount(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        \App\Telegram\Conversations\User\DeleteAccountConversation::begin($bot);
    }

    // ============================================================
    //  Helpers
    // ============================================================


    private function getUser(Nutgram $bot): ?User
    {
        return User::where('telegram_id', $bot->userId())->first();
    }

    private function safeEditOrSend(Nutgram $bot, string $text, $keyboard): void
    {
        try {
            $bot->editMessageText(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
                disable_web_page_preview: true,
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'message is not modified')) {
                return;
            }

            try {
                $bot->sendMessage(
                    text: $text,
                    parse_mode: 'HTML',
                    reply_markup: $keyboard,
                    disable_web_page_preview: true,
                );
            } catch (\Throwable $e2) {
                Log::warning('UserDashboardHandler safeEdit failed', [
                    'error' => $e2->getMessage(),
                ]);
            }
        }
    }
}
