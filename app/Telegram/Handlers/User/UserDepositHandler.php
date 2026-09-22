<?php

namespace App\Telegram\Handlers\User;

use App\Models\DepositMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DepositService;
use App\Telegram\Keyboards\User\UserDepositKeyboard;
use App\Telegram\Screens\User\UserDepositScreen;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class UserDepositHandler
{
    public function __construct(
        private readonly DepositService $depositService,
    ) {}

    // ============================================================
    //  💳 قائمة الطرق
    // ============================================================

    public function showMethods(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        $methods = DepositMethod::active()->ordered()->get();

        if ($methods->isEmpty()) {
            $bot->sendMessage(
                text: implode("\n", [
                    '💰 <b>شحن المحفظة</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '⚠️ لا توجد طرق إيداع متاحة حالياً.',
                ]),
                parse_mode: 'HTML',
                reply_markup: UserDepositKeyboard::back(),
            );
            return;
        }

        $this->safeEdit(
            $bot,
            UserDepositScreen::chooseMethod(),
            UserDepositKeyboard::methods($methods),
        );
    }

    // ============================================================
    //  ✅ تأكيد نهائي (من Conversation)
    // ============================================================

    public function confirm(
        Nutgram $bot,
        User $user,
        DepositMethod $method,
        float $amount,
        string $transactionId,
        ?string $proofFile = null,
    ): Transaction {
        $result = $this->depositService->createRequest(
            user: $user,
            method: $method,
            amount: $amount,
            transactionId: $transactionId,
            proofFile: $proofFile,
        );

        if (! $result['success']) {
            throw new \RuntimeException($result['error'] ?? 'فشل إنشاء الطلب');
        }

        return $result['transaction'];
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function safeEdit(Nutgram $bot, string $text, $keyboard): void
    {
        try {
            $bot->editMessageText(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'message is not modified')) {
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
