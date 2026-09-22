<?php

namespace App\Telegram\Handlers\User;

use App\Models\User;
use App\Services\GemService;
use App\Services\NotificationService;
use App\Telegram\Keyboards\User\UserGemKeyboard;
use App\Telegram\Screens\User\UserGemScreen;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class UserGemHandler
{
    public function __construct(
        private readonly GemService $gemService,
    ) {}

    /**
     * 🏠 الشاشة الرئيسية
     */
    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $user = $this->getUser($bot);

        if (! $user) {
            $this->safeAlert($bot, '❌ تعذر التعرف على حسابك');
            return;
        }

        if (! $this->gemService->isEnabled()) {
            $this->safeEdit(
                $bot,
                '💎 <b>نظام الجواهر</b>' . "\n\n" . '⚠️ النظام معطّل حاليًا.',
            );
            return;
        }

        $this->safeEdit(
            $bot,
            UserGemScreen::main($user, $this->gemService),
            UserGemKeyboard::main($user, $this->gemService),
        );
    }

    /**
     * 💱 شاشة اختيار العملة
     */
    public function exchange(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $user = $this->getUser($bot);

        if (! $user) return;

        $balance = $this->gemService->getBalanceInt($user);
        $minGems = $this->gemService->getExchangeMinGems();

        if ($balance < $minGems) {
            $this->safeAlert(
                $bot,
                "❌ تحتاج {$minGems} جوهرة على الأقل",
            );
            return;
        }

        $this->safeEdit(
            $bot,
            UserGemScreen::exchangeChoice($user, $this->gemService),
            UserGemKeyboard::exchangeChoice(),
        );
    }

    /**
     * ✅ تنفيذ الاستبدال
     */
    public function confirmExchange(Nutgram $bot, string $currency): void
    {
        $currency = strtoupper($currency);

        $user = $this->getUser($bot);

        if (! $user) {
            $this->safeAlert($bot, '❌ تعذر التعرف على حسابك');
            return;
        }

        $result = $this->gemService->exchangeToBalance($user, $currency);

        if (! $result['success']) {
            $this->safeAlert($bot, '❌ ' . $result['error']);
            return;
        }

        $gemsUsed = $this->gemService->getExchangeMinGems();
        $balance  = $this->gemService->getBalanceInt($user);
        $valueNsp = $this->gemService->getExchangeValueNsp();

        // احسب الجواهر المستخدمة (كل الجواهر الحالية)
        $gemsUsed = $result['amount'] * $gemsUsed / $valueNsp;

        $this->safeEdit(
            $bot,
            UserGemScreen::exchangeSuccess(
                $result['currency'],
                $result['amount'],
                (int) round($gemsUsed),
            ),
            UserGemKeyboard::success(),
        );

        // 📢 إشعار القناة
        $this->notifyChannelExchange($bot, $user, $result);

        Log::info('User exchanged gems', [
            'user_id'  => $user->id,
            'amount'   => $result['amount'],
            'currency' => $result['currency'],
        ]);
    }

    /**
     * 🎡 فتح العجلة
     */
    public function wheel(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $user = $this->getUser($bot);

        if (! $user) {
            $this->safeAlert($bot, '❌ تعذر التعرف على حسابك');
            return;
        }

        $result = $this->gemService->exchangeToWheelSpins($user);

        if (! $result['success']) {
            $this->safeAlert($bot, '❌ ' . $result['error']);
            return;
        }

        $this->safeEdit(
            $bot,
            UserGemScreen::wheelSuccess($result['spins'], $result['gems_spent']),
            UserGemKeyboard::wheelSuccess(),
        );

        // 📢 إشعار القناة
        $this->notifyChannelWheel($bot, $user, $result);

        Log::info('User opened wheel with gems', [
            'user_id' => $user->id,
            'spins'   => $result['spins'],
            'gems'    => $result['gems_spent'],
        ]);
    }

    /**
     * 📜 سجل الجواهر
     */
    public function history(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $user = $this->getUser($bot);

        if (! $user) return;

        $this->safeEdit(
            $bot,
            UserGemScreen::history($user),
            UserGemKeyboard::history(),
        );
    }

    /**
     * 🚫 زر غير فعّال
     */
    public function noop(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
    }

    // ═══════════════════════════════════════════════════════════
    //  الإشعارات
    // ═══════════════════════════════════════════════════════════

    private function notifyChannelExchange(Nutgram $bot, User $user, array $result): void
    {
        try {
            $text = implode("\n", [
                '💎 <b>استبدال جواهر</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👤 <b>المستخدم:</b> <code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
                '🆔 <b>المعرف:</b> <code>' . $user->id . '</code>',
                '',
                '💰 <b>المبلغ:</b> <b>' . number_format($result['amount'], 2) . ' ' . $result['currency'] . '</b>',
                '',
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            app(NotificationService::class)->notifyTransactionsChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about gem exchange', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyChannelWheel(Nutgram $bot, User $user, array $result): void
    {
        try {
            $text = implode("\n", [
                '🎡 <b>فتح عجلة بالجواهر</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👤 <b>المستخدم:</b> <code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
                '',
                '💎 <b>الجواهر:</b> ' . $result['gems_spent'],
                '🎰 <b>اللفات:</b> ' . $result['spins'],
                '',
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            app(NotificationService::class)->notifyGeneralChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about gem wheel', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════

    private function getUser(Nutgram $bot): ?User
    {
        return User::where('telegram_id', $bot->userId())->first();
    }

    private function safeAnswer(Nutgram $bot, ?string $text = null): void
    {
        try {
            $bot->answerCallbackQuery(text: $text);
        } catch (\Throwable $e) {
        }
    }

    private function safeAlert(Nutgram $bot, string $text): void
    {
        try {
            $bot->answerCallbackQuery(text: $text, show_alert: true);
        } catch (\Throwable $e) {
        }
    }

    private function safeEdit(Nutgram $bot, string $text, $keyboard = null): void
    {
        try {
            $bot->editMessageText(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
                disable_web_page_preview: true,
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not modified')) {
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
            }
        }
    }
}
