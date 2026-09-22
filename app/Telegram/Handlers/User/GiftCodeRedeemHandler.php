<?php

namespace App\Telegram\Handlers\User;

use App\Models\User;
use App\Services\GiftCodeService;
use App\Telegram\Keyboards\User\GiftCodeRedeemKeyboard;
use App\Telegram\Screens\User\GiftCodeRedeemScreen;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class GiftCodeRedeemHandler
{
    /**
     * مفتاح Cache — يُفعَّل عند ضغط المستخدم على زر "🎁 كود هدية"
     */
    private const CACHE_KEY_PREFIX = 'gift_redeem_mode_';
    private const CACHE_TTL        = 300; // 5 دقائق

    // ============================================================
    //  🎁 عند الضغط على زر "🎁 كود هدية"
    // ============================================================
    public function show(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        // ✅ فعّل حالة "انتظار الكود" لهذا المستخدم
        Cache::put(
            self::CACHE_KEY_PREFIX . $bot->userId(),
            true,
            self::CACHE_TTL,
        );

        Log::info('GiftCode: redeem mode activated', [
            'telegram_id' => $bot->userId(),
        ]);

        $bot->sendMessage(
            text: GiftCodeRedeemScreen::prompt(),
            parse_mode: 'HTML',
            reply_markup: GiftCodeRedeemKeyboard::prompt(),
        );
    }

    // ============================================================
    //  ✍️ عند كتابة الكود كنص
    // ============================================================
    public function handleText(Nutgram $bot, string $code): void
    {
        $userId   = $bot->userId();
        $codeText = strtoupper(trim($code));

        // ============================================================
        //  🛡️ فحص الحالة — هل ضغط المستخدم على الزر؟
        // ============================================================
        if (! Cache::has(self::CACHE_KEY_PREFIX . $userId)) {
            // ❌ لم يضغط على الزر → تجاهل صامت (بدون أي رسالة)
            Log::info('GiftCode: ignored text without redeem mode', [
                'telegram_id' => $userId,
                'code'        => $codeText,
            ]);

            return;
        }

        // ✅ الحالة صحيحة → امسحها (لمنع التكرار)
        Cache::forget(self::CACHE_KEY_PREFIX . $userId);

        // ✅ ثم استبدل الكود
        $this->redeem($bot, $codeText);
    }

    // ============================================================
    //  🎯 المنطق الأساسي للاستبدال
    // ============================================================
    protected function redeem(Nutgram $bot, string $codeText): void
    {
        if (! preg_match('/^GIFT_[A-Z0-9]{4,16}$/', $codeText)) {
            $bot->sendMessage(
                text: "❌ صيغة الكود غير صحيحة.\n\nيجب أن يبدأ بـ <code>GIFT_</code>",
                parse_mode: 'HTML',
            );
            return;
        }

        $user = User::where('telegram_id', $bot->userId())->first();

        if (! $user) {
            $bot->sendMessage(text: '❌ يجب عليك التسجيل أولاً. استخدم /start');
            return;
        }

        if (! $user->is_active) {
            $bot->sendMessage(text: '🚫 حسابك موقوف. تواصل مع الدعم.');
            return;
        }

        // ============================================================
        //  🛡️ حماية: منع الأدمن والسوبر أدمن
        // ============================================================
        if ($user->is_admin || $user->is_super_admin) {
            $bot->sendMessage(
                text: implode("\n", [
                    '🚫 <b>غير مسموح</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    'هذه الميزة مخصصة للمستخدمين فقط.',
                    '',
                    '🎯 أنت ' . ($user->is_super_admin ? 'سوبر أدمن' : 'أدمن') . '.',
                    '',
                    '💡 إذا كنت تريد اختبار الأكواد، استخدم حساب مستخدم آخر.',
                ]),
                parse_mode: 'HTML',
            );
            return;
        }

        // رسالة الانتظار
        $waitMsg = $bot->sendMessage(
            text: '⏳ <b>جاري التحقق من الكود...</b>',
            parse_mode: 'HTML',
        );

        try {
            $service = app(GiftCodeService::class);
            $result = $service->redeem($user->id, $codeText);

            // حذف رسالة الانتظار
            $this->deleteMessage($bot, $waitMsg?->message_id);

            if ($result['ok']) {
                $bot->sendMessage(
                    text: GiftCodeRedeemScreen::success($result['value'], $result['currency']),
                    parse_mode: 'HTML',
                    reply_markup: GiftCodeRedeemKeyboard::success(),
                );

                $this->updateChannelMessage($bot, $result['gift']);
            } else {
                $bot->sendMessage(
                    text: $result['msg'],
                    parse_mode: 'HTML',
                    reply_markup: GiftCodeRedeemKeyboard::prompt(),
                );
            }
        } catch (\Throwable $e) {
            Log::error('GiftCode redeem failed', [
                'user_id' => $user->id,
                'code'    => $codeText,
                'error'   => $e->getMessage(),
            ]);

            $this->deleteMessage($bot, $waitMsg?->message_id);

            $bot->sendMessage(text: '❌ حدث خطأ غير متوقع. حاول لاحقاً.');
        }
    }

    // ============================================================
    //  🎁 تحديث رسالة القناة بعد الاستبدال
    // ============================================================
    protected function updateChannelMessage(Nutgram $bot, $gift): void
    {
        if (! $gift->channel_id || ! $gift->channel_msg_id) {
            return;
        }

        try {
            $remaining = max(0, $gift->max_uses - $gift->used_count);

            $text = implode("\n", [
                '🎁 <b>كود هدية</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🔐 الكود: <code>' . $gift->code . '</code>',
                '💰 القيمة: <b>' . $gift->value . ' ' . $gift->currency . '</b>',
                $gift->status === 'used' || $remaining === 0
                    ? '✅ <b>تم استخدام هذا الكود بالكامل</b>'
                    : '👥 متبقٍ: <b>' . $remaining . '</b> مستخدم',
            ]);

            $bot->editMessageText(
                text: $text,
                chat_id: $gift->channel_id,
                message_id: $gift->channel_msg_id,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to update channel message', [
                'gift_id' => $gift->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  🗑 حذف رسالة
    // ============================================================
    protected function deleteMessage(Nutgram $bot, ?int $messageId): void
    {
        if (! $messageId) {
            return;
        }

        try {
            $bot->deleteMessage(
                chat_id: $bot->chatId(),
                message_id: $messageId,
            );
        } catch (\Throwable $e) {
        }
    }
}
