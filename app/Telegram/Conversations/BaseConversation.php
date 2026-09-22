<?php

namespace App\Telegram\Conversations;

use Illuminate\Support\Facades\Cache;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * 📦 Base Conversation — تتبّع ذكي
 *
 *  - askTracked()  → رسالة تُحذف عند النهاية
 *  - keep()        → رسالة تبقى عند النهاية
 *  - endAndClean() → يحذف "المُتتبّعة" فقط
 */
abstract class BaseConversation extends Conversation
{
    // ═══════════════════════════════════════════════════════════
    //  🔑 مفاتيح الكاش
    // ═══════════════════════════════════════════════════════════

    protected function trackedKey(): string
    {
        return 'conv_tracked_' . ($this->userId ?? 0) . '_' . ($this->chatId ?? 0);
    }

    // ═══════════════════════════════════════════════════════════
    //  📥 تسجيل رسالة واردة (رسالة المستخدم)
    // ═══════════════════════════════════════════════════════════

    public function __invoke(Nutgram $bot, ...$parameters): mixed
    {
        // سجّل رسالة المستخدم (ستُحذف عند النهاية)
        $msgId = $bot->messageId();
        if ($msgId) {
            $this->rememberId($msgId);
        }

        return parent::__invoke($bot, ...$parameters);
    }

    // ═══════════════════════════════════════════════════════════
    //  🗑️ askTracked — رسالة تُحذف لاحقًا
    // ═══════════════════════════════════════════════════════════

    protected function askTracked(Nutgram $bot, string $text, mixed ...$args): mixed
    {
        $msg = $bot->sendMessage($text, ...$args);
        if ($msg?->message_id) {
            $this->rememberId($msg->message_id);
        }
        return $msg;
    }

    // ═══════════════════════════════════════════════════════════
    //  ✅ keep — رسالة تبقى دائمًا
    // ═══════════════════════════════════════════════════════════

    protected function keep(Nutgram $bot, string $text, mixed ...$args): mixed
    {
        return $bot->sendMessage($text, ...$args);
    }

    // ═══════════════════════════════════════════════════════════
    //  🧹 التتبّع
    // ═══════════════════════════════════════════════════════════

    protected function rememberId(int $msgId): void
    {
        $key = $this->trackedKey();
        $ids = Cache::get($key, []) ?? [];
        $ids[] = $msgId;
        Cache::put($key, $ids, now()->addHours(2));
    }

    // ═══════════════════════════════════════════════════════════
    //  🏁 endAndClean — يحذف المُتتبّعة فقط
    // ═══════════════════════════════════════════════════════════

    protected function endAndClean(Nutgram $bot): void
    {
        $this->cleanupTracked($bot);
        $this->end();
    }

    protected function cleanupTracked(Nutgram $bot): void
    {
        $key     = $this->trackedKey();
        $ids     = Cache::get($key, []) ?? [];
        $chatId  = $bot->chatId();
        $current = $bot->messageId();

        foreach ($ids as $id) {
            if ($id === $current) continue; // لا تحذف رسالة الوارد الحالية

            try {
                $bot->deleteMessage($chatId, $id);
            } catch (\Throwable $e) {
                // تجاهل
            }
        }

        Cache::forget($key);
    }
}
