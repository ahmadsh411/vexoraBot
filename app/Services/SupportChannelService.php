<?php

namespace App\Services;

use App\Models\SupportAgent;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class SupportChannelService
{
    // ============================================================
    //  📢 القنوات الثلاثة
    // ============================================================
    public const CHANNELS = [
        'users_channel_id',
        'transactions_channel_id',
        'general_channel_id',
    ];

    // ============================================================
    //  📋 الحصول على روابط القنوات
    // ============================================================
    public function getChannelLinks(): array
    {
        $channels = [];
        $bot = app(Nutgram::class);

        $names = [
            'users_channel_id'        => 'قناة المستخدمين',
            'transactions_channel_id' => 'قناة العمليات',
            'general_channel_id'      => 'القناة العامة',
        ];

        foreach (self::CHANNELS as $key) {
            $channelId = config("services.telegram.{$key}");

            if (empty($channelId)) {
                Log::warning("SupportChannelService: {$key} not configured");
                continue;
            }

            $link = $this->getInviteLink($bot, $key, (int) $channelId);

            if ($link) {
                $channels[] = [
                    'key'  => $key,
                    'name' => $names[$key] ?? $key,
                    'url'  => $link,
                    'id'   => $channelId,
                ];
            }
        }

        return $channels;
    }

    // ============================================================
    //  🔗 إنشاء رابط دعوة
    // ============================================================
    private function getInviteLink(Nutgram $bot, string $channelKey, int $channelId): ?string
    {
        // ─── 1. رابط ثابت من config ───
        $staticLink = config("services.telegram.{$channelKey}_link");

        if (! empty($staticLink)) {
            return $staticLink;
        }

        // ─── 2. إنشاء رابط ديناميكي ───
        try {
            $inviteLink = $bot->createChatInviteLink(
                chat_id: $channelId,
                name: 'دعم - ' . now()->format('Y-m-d H:i'),
                creates_join_request: false,
            );

            if ($inviteLink && $inviteLink->invite_link) {
                return $inviteLink->invite_link;
            }
        } catch (\Throwable $e) {
            Log::warning('SupportChannelService: createChatInviteLink failed', [
                'channel'    => $channelKey,
                'channel_id' => $channelId,
                'error'      => $e->getMessage(),
            ]);
        }

        return null;
    }

    // ============================================================
    //  ✅ إرسال رسالة الاشتراك + حفظ message_id
    // ============================================================
    public function sendJoinMessage(SupportAgent $agent): bool
    {
        if (! $agent->hasTelegramId()) {
            return false;
        }

        try {
            // ✅ 1. احذف الرسالة القديمة إن وُجدت
            $this->deleteJoinMessage($agent);

            $bot = app(Nutgram::class);
            $channels = $this->getChannelLinks();

            if (empty($channels)) {
                return false;
            }

            // ─── الرسالة ───
            $lines = [
                '🎧 <b>مرحباً بك في فريق الدعم</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👤 <b>الاسم:</b> ' . htmlspecialchars($agent->name, ENT_QUOTES, 'UTF-8'),
                '🆔 <b>المعرّف:</b> <code>' . $agent->username . '</code>',
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📢 <b>للانضمام لفريق الدعم:</b>',
                '',
                'يرجى الاشتراك في القنوات التالية:',
                '',
            ];

            foreach ($channels as $i => $channel) {
                $num = $i + 1;
                $lines[] = "{$num}️⃣ <b>{$channel['name']}</b>";
                $lines[] = '   <a href="' . $channel['url'] . '">اضغط للاشتراك</a>';
                $lines[] = '';
            }

            $lines[] = '━━━━━━━━━━━━━━━━━━';
            $lines[] = '';
            $lines[] = '📬 <i>بعد الاشتراك ستصلك الإشعارات تلقائياً.</i>';

            $text = implode("\n", $lines);

            // ─── الأزرار ───
            $keyboard = \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup::make();

            foreach ($channels as $channel) {
                $keyboard->addRow(
                    \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                        text: '📢 ' . $channel['name'],
                        url: $channel['url'],
                    ),
                );
            }

            $keyboard->addRow(
                \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                    text: '✅ تم الاشتراك',
                    callback_data: 'support.join.confirm.' . $agent->id,
                    style: \SergiX44\Nutgram\Telegram\Properties\ButtonStyle::SUCCESS,
                ),
            );

            // ─── الإرسال ───
            $sentMessage = $bot->sendMessage(
                text: $text,
                chat_id: $agent->telegram_id,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
                disable_web_page_preview: true,
            );

            // ✅ 2. احفظ message_id
            if ($sentMessage && $sentMessage->message_id) {
                $agent->update([
                    'join_message_id' => $sentMessage->message_id,
                ]);

                Log::info('SupportChannelService: join message sent', [
                    'agent_id'   => $agent->id,
                    'message_id' => $sentMessage->message_id,
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('SupportChannelService: sendJoinMessage failed', [
                'agent_id' => $agent->id,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ============================================================
    //  🗑️ حذف رسالة الاشتراك من محادثة الداعم
    // ============================================================
    public function deleteJoinMessage(SupportAgent $agent): bool
    {
        if (! $agent->hasTelegramId()) {
            return false;
        }

        if (empty($agent->join_message_id)) {
            Log::debug('SupportChannelService: no join_message_id to delete', [
                'agent_id' => $agent->id,
            ]);
            return false;
        }

        try {
            $bot = app(Nutgram::class);

            $bot->deleteMessage(
                chat_id: $agent->telegram_id,
                message_id: (int) $agent->join_message_id,
            );

            $agent->update(['join_message_id' => null]);

            Log::info('SupportChannelService: join message deleted', [
                'agent_id'   => $agent->id,
                'message_id' => $agent->join_message_id,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('SupportChannelService: failed to delete join message', [
                'agent_id' => $agent->id,
                'error'    => $e->getMessage(),
            ]);

            // ─── حتى لو فشل الحذف → صفّر message_id ───
            $agent->update(['join_message_id' => null]);

            return false;
        }
    }

    // ============================================================
    //  🗑️ إزالة الداعم من القنوات
    // ============================================================
    public function removeFromAllChannels(SupportAgent $agent): array
    {
        if (! $agent->hasTelegramId()) {
            return ['error' => 'no_telegram_id'];
        }

        $bot = app(Nutgram::class);
        $results = [];

        foreach (self::CHANNELS as $key) {
            $channelId = config("services.telegram.{$key}");

            if (empty($channelId)) {
                $results[$key] = 'not_configured';
                continue;
            }

            try {
                $bot->banChatMember(
                    chat_id: (int) $channelId,
                    user_id: $agent->telegram_id,
                );

                $bot->unbanChatMember(
                    chat_id: (int) $channelId,
                    user_id: $agent->telegram_id,
                    only_if_banned: true,
                );

                $results[$key] = 'removed';

                Log::info('SupportChannelService: removed from channel', [
                    'agent_id'    => $agent->id,
                    'channel'     => $key,
                    'telegram_id' => $agent->telegram_id,
                ]);
            } catch (\Throwable $e) {
                $results[$key] = 'failed: ' . $e->getMessage();

                Log::warning('SupportChannelService: failed to remove', [
                    'agent_id' => $agent->id,
                    'channel'  => $key,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }
}
