<?php

namespace App\Telegram\Handlers\Admin\GiftCode;

use App\Models\GiftCode;
use App\Telegram\Keyboards\AdminsKeyboard\GiftCode\GiftCodesKeyboard;
use App\Telegram\Screens\Admin\GiftCode\GiftCodesScreen;
use SergiX44\Nutgram\Nutgram;

class GiftCodeAdminHandler
{
    /**
     * الصفحة الرئيسية
     */
    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            GiftCodesScreen::index(),
            GiftCodesKeyboard::main(),
        );
    }

    /**
     * قائمة الأكواد النشطة
     */
    public function active(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $codes = GiftCode::where('status', 'active')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $this->safeEdit(
            $bot,
            GiftCodesScreen::activeList($codes),
            GiftCodesKeyboard::activeList($codes),
        );
    }

    /**
     * سجل الاستبدالات (بدون كشف هوية المستخدمين)
     */
    public function history(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        // نجلب فقط الأكواد المستخدمة (بدون user_id أو username)
        $codes = GiftCode::where('status', 'used')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $this->safeEdit(
            $bot,
            GiftCodesScreen::historyList($codes),
            GiftCodesKeyboard::historyList(),
        );
    }

    /**
     * تعطيل كود
     */
    public function disable(Nutgram $bot, int $id): void
    {
        $code = GiftCode::find($id);

        if (! $code) {
            $bot->answerCallbackQuery(text: '❌ الكود غير موجود.', show_alert: true);
            return;
        }

        if ($code->status !== 'active') {
            $bot->answerCallbackQuery(text: '⚠️ الكود ليس نشطاً.', show_alert: true);
            return;
        }

        $code->update(['status' => 'disabled']);

        $bot->answerCallbackQuery(text: '✅ تم تعطيل الكود.', show_alert: true);

        // تحديث القائمة
        $this->active($bot);
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

            $bot->sendMessage(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
            );
        }
    }
}
