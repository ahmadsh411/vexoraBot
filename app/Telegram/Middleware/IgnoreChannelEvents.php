<?php

namespace App\Telegram\Middleware;

use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class IgnoreChannelEvents
{
    /**
     * 🛡️ تجاهل كل الأحداث من القنوات والمجموعات
     */
    public function __invoke(Nutgram $bot, $next): void
    {
        $update = $bot->update();

        if ($update === null) {
            return;
        }

        // ═══════════════════════════════════════════════════════
        //  [1] تجاهل chat_member / my_chat_member
        //  ← مصدر المشكلة الرئيسي
        // ═══════════════════════════════════════════════════════
        $isChatMember = false;

        if (property_exists($update, 'chat_member') && $update->chat_member !== null) {
            $isChatMember = true;
        }

        if (property_exists($update, 'my_chat_member') && $update->my_chat_member !== null) {
            $isChatMember = true;
        }

        if ($isChatMember) {
            Log::debug('IgnoreChannelEvents: ignored chat_member', [
                'chat_id' => $bot->chatId(),
            ]);
            return;
        }

        // ═══════════════════════════════════════════════════════
        //  [2] تجاهل channel_post / edited_channel_post
        // ═══════════════════════════════════════════════════════
        $isChannelPost = false;

        if (property_exists($update, 'channel_post') && $update->channel_post !== null) {
            $isChannelPost = true;
        }

        if (property_exists($update, 'edited_channel_post') && $update->edited_channel_post !== null) {
            $isChannelPost = true;
        }

        if ($isChannelPost) {
            Log::debug('IgnoreChannelEvents: ignored channel_post', [
                'chat_id' => $bot->chatId(),
            ]);
            return;
        }

        // ═══════════════════════════════════════════════════════
        //  [3] تجاهل الرسائل من القنوات والمجموعات
        // ═══════════════════════════════════════════════════════
        $chatType = $bot->update()?->message?->chat?->type
            ?? $bot->update()?->callbackQuery?->message?->chat?->type
            ?? $bot->update()?->editedMessage?->chat?->type
            ?? $bot->chat()?->type;

        if (in_array($chatType, ['channel', 'group', 'supergroup'], true)) {
            Log::debug('IgnoreChannelEvents: blocked', [
                'chat_id'     => $bot->chatId(),
                'chat_type'   => $chatType,
                'update_type' => $this->detectUpdateType($bot),
            ]);

            return;
        }

        // ═══════════════════════════════════════════════════════
        //  [4] تجاهل أي update غير مدعوم
        // ═══════════════════════════════════════════════════════
        $updateType = $this->detectUpdateType($bot);

        $allowedTypes = [
            'message',
            'callback_query',
            'inline_query',
            'pre_checkout_query',
            'edited_message',
        ];

        if (! in_array($updateType, $allowedTypes, true)) {
            Log::debug('IgnoreChannelEvents: ignored unsupported update', [
                'type' => $updateType,
            ]);
            return;
        }

        $next($bot);
    }

    /**
     * 🔍 تحديد نوع الـ Update
     */
    private function detectUpdateType(Nutgram $bot): string
    {
        try {
            $update = $bot->update();

            if ($update === null) return 'unknown';

            // ✅ chat_member أولاً
            if (property_exists($update, 'chat_member') && $update->chat_member !== null) {
                return 'chat_member';
            }

            if (property_exists($update, 'my_chat_member') && $update->my_chat_member !== null) {
                return 'my_chat_member';
            }

            if (property_exists($update, 'channel_post') && $update->channel_post !== null) {
                return 'channel_post';
            }

            if (property_exists($update, 'edited_channel_post') && $update->edited_channel_post !== null) {
                return 'edited_channel_post';
            }

            // ✅ message types
            if ($bot->message()) return 'message';
            if ($bot->callbackQuery()) return 'callback_query';
            if ($bot->editedMessage()) return 'edited_message';
            if ($bot->inlineQuery()) return 'inline_query';
            if ($bot->preCheckoutQuery()) return 'pre_checkout_query';
        } catch (\Throwable $e) {
            // تجاهل
        }

        return 'unknown';
    }
}
