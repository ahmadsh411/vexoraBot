<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class AdminChannelSyncService
{
    /**
     * القنوات التي يجب أن يكون فيها الأدمن
     */
    public const ADMIN_CHANNELS = [
        'transactions_channel_id',
        'general_channel_id',
        'users_channel_id',
    ];

    // ============================================================
    //  🚪 طرد الأدمن من كل القنوات
    // ============================================================
    public function removeFromAllChannels(User $user): array
    {
        if (! $user->telegram_id) {
            Log::warning('AdminChannelSyncService: no telegram_id', [
                'user_id' => $user->id,
            ]);
            return ['error' => 'no_telegram_id'];
        }

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $results = [];

        foreach (self::ADMIN_CHANNELS as $channelKey) {
            $channelId = config("services.telegram.{$channelKey}");

            if (! $channelId) {
                $results[$channelKey] = 'not_configured';
                continue;
            }

            try {
                // ✅ 1. اطرد المستخدم
                $bot->banChatMember(
                    chat_id: (int) $channelId,
                    user_id: $user->telegram_id,
                );

                // ✅ 2. ألغِ الحظر (حتى يتمكن من العودة إذا أُعيد تعيينه)
                $bot->unbanChatMember(
                    chat_id: (int) $channelId,
                    user_id: $user->telegram_id,
                    only_if_banned: true,
                );

                $results[$channelKey] = 'removed';

                Log::info('AdminChannelSyncService: removed from channel', [
                    'user_id'    => $user->id,
                    'channel'    => $channelKey,
                ]);
            } catch (\Throwable $e) {
                $results[$channelKey] = 'failed: ' . $e->getMessage();

                Log::warning('AdminChannelSyncService: failed to remove', [
                    'user_id' => $user->id,
                    'channel' => $channelKey,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        Log::info('AdminChannelSyncService: removeFromAllChannels done', [
            'user_id' => $user->id,
            'results' => $results,
        ]);

        return $results;
    }

    // ============================================================
    //  📊 للتحقق: أين المستخدم الآن؟
    // ============================================================
    public function checkUserChannels(User $user): array
    {
        if (! $user->telegram_id) {
            return ['error' => 'no_telegram_id'];
        }

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $results = [];

        foreach (self::ADMIN_CHANNELS as $channelKey) {
            $channelId = config("services.telegram.{$channelKey}");

            if (! $channelId) {
                $results[$channelKey] = 'not_configured';
                continue;
            }

            try {
                $member = $bot->getChatMember(
                    chat_id: (int) $channelId,
                    user_id: $user->telegram_id,
                );

                $status = $member->status->value ?? (string) $member->status;

                $results[$channelKey] = [
                    'status'    => $status,
                    'is_member' => in_array($status, ['member', 'administrator', 'creator']),
                ];
            } catch (\Throwable $e) {
                $results[$channelKey] = [
                    'status'    => 'not_member',
                    'is_member' => false,
                ];
            }
        }

        return $results;
    }
}
