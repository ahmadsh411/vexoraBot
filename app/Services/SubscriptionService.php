<?php

namespace App\Services;

use App\Models\SupportAgent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class SubscriptionService
{
    // ============================================================
    //  ✅ التحقق من الاشتراك في القناة الرسمية
    // ============================================================
    public function isSubscribed(int $telegramId): bool
    {
        // ✅ الأدمن — لا يحتاج
        $user = User::where('telegram_id', $telegramId)->first();

        if ($user && $user->is_admin) {
            return true;
        }

        // ✅ الداعم — لا يحتاج
        if (SupportAgent::where('telegram_id', $telegramId)->exists()) {
            return true;
        }

        // ─── التحقق من القناة ───
        $channelId = config('services.telegram.main_channel_id');

        if (empty($channelId)) {
            Log::warning('SubscriptionService: main_channel_id not configured');
            return false; // افترض عدم الاشتراك — حماية
        }

        try {
            /** @var Nutgram $bot */
            $bot = app(Nutgram::class);

            $member = $bot->getChatMember(
                chat_id: (int) $channelId,
                user_id: $telegramId,
            );

            $status = $member->status->value ?? (string) $member->status;

            $isSubscribed = in_array($status, ['member', 'administrator', 'creator'], true);

            Log::debug('SubscriptionService: check', [
                'telegram_id' => $telegramId,
                'status'      => $status,
                'subscribed'  => $isSubscribed,
            ]);

            return $isSubscribed;
        } catch (\Throwable $e) {
            Log::warning('SubscriptionService: getChatMember failed', [
                'telegram_id' => $telegramId,
                'channel_id'  => $channelId,
                'error'       => $e->getMessage(),
            ]);

            // ⚠️ إذا فشل الاتصال — اعتبره غير مشترك (أكثر أماناً)
            return false;
        }
    }

    // ============================================================
    //  🔗 رابط القناة
    // ============================================================
    public function getChannelLink(): string
    {
        return config('services.telegram.main_channel_link')
            ?? 'https://t.me/+SwaqXxyD-fgyZjg0';
    }

    // ============================================================
    //  📨 إرسال رسالة الاشتراك
    // ============================================================
    public function sendSubscriptionMessage(Nutgram $bot): void
    {
        $link = $this->getChannelLink();

        $keyboard = \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup::make()
            ->addRow(
                \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                    text: '📢 اشترك في القناة الرسمية',
                    url: $link,
                    style: \SergiX44\Nutgram\Telegram\Properties\ButtonStyle::PRIMARY,
                ),
            )
            ->addRow(
                \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                    text: '✅ تحقق مرة أخرى',
                    callback_data: 'check.subscription',
                    style: \SergiX44\Nutgram\Telegram\Properties\ButtonStyle::SUCCESS,
                ),
            );

        $text = implode("\n", [
            '⚠️ <b>يجب الاشتراك في القناة الرسمية</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📢 <b>للاشتراك:</b>',
            'اضغط على الزر أدناه.',
            '',
            '✅ <b>ثم اضغط "تحقق مرة أخرى"</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💡 <i>بعد التحقق، يمكنك استخدام البوت.</i>',
        ]);

        try {
            $bot->sendMessage(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
                disable_web_page_preview: true,
            );
        } catch (\Throwable $e) {
            Log::warning('SubscriptionService: send message failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  🎧 التحقق من اشتراك الأدمن/الداعم في القنوات الثلاثة
    //  يُرجع القنوات الناقصة (فارغ = مشترك في الكل)
    // ============================================================
    public function getMissingAdminChannels(int $telegramId): array
    {
        $channels = [
            'users_channel_id'        => [
                'name' => '👥 قناة المستخدمين',
                'link' => \App\Services\AdminWelcomeService::INVITE_LINKS['users'] ?? null,
            ],
            'transactions_channel_id' => [
                'name' => '💰 قناة التحويلات',
                'link' => \App\Services\AdminWelcomeService::INVITE_LINKS['transactions'] ?? null,
            ],
            'general_channel_id'      => [
                'name' => '📢 القناة العامة',
                'link' => \App\Services\AdminWelcomeService::INVITE_LINKS['general'] ?? null,
            ],
        ];

        $missing = [];

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);

        foreach ($channels as $key => $info) {
            $channelId = config("services.telegram.{$key}");

            if (empty($channelId)) {
                continue;
            }

            try {
                $member = $bot->getChatMember(
                    chat_id: (int) $channelId,
                    user_id: $telegramId,
                );

                $status = $member->status->value ?? (string) $member->status;

                $isMember = in_array($status, ['member', 'administrator', 'creator'], true);

                if (! $isMember) {
                    $missing[$key] = $info;
                }
            } catch (\Throwable $e) {
                // ⚠️ فشل التحقق → اعتبره غير مشترك
                $missing[$key] = $info;

                Log::warning('SubscriptionService: getMissingAdminChannels failed', [
                    'telegram_id' => $telegramId,
                    'channel'     => $key,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return $missing;
    }

    // ============================================================
    //  📨 إرسال رسالة "اشترك في القنوات الناقصة"
    // ============================================================
    public function sendMissingChannelsMessage(Nutgram $bot, array $missing, ?int $agentId = null): void
    {
        if (empty($missing)) {
            return;
        }

        $lines = [
            '⚠️ <b>لم تشترك في كل القنوات</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📢 <b>يجب الاشتراك في القنوات التالية:</b>',
            '',
        ];

        foreach ($missing as $key => $info) {
            $lines[] = '❌ ' . $info['name'];
        }

        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━';
        $lines[] = '';
        $lines[] = '💡 <i>بعد الاشتراك، اضغط "✅ تم الاشتراك" مرة أخرى.</i>';

        // ─── الأزرار ───
        $keyboard = \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup::make();

        foreach ($missing as $key => $info) {
            if (! empty($info['link'])) {
                $keyboard->addRow(
                    \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                        text: '📢 اشترك: ' . $info['name'],
                        url: $info['link'],
                    ),
                );
            }
        }

        $callbackData = $agentId
            ? 'support.join.confirm.' . $agentId
            : 'support.join.confirm.' . ($bot->userId() ?? '');

        $keyboard->addRow(
            \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                text: '✅ تم الاشتراك',
                callback_data: $callbackData,
                style: \SergiX44\Nutgram\Telegram\Properties\ButtonStyle::SUCCESS,
            ),
        );

        try {
            $sentMessage = $bot->sendMessage(
                text: implode("\n", $lines),
                parse_mode: 'HTML',
                reply_markup: $keyboard,
                disable_web_page_preview: true,
            );

            // ✅ احفظ message_id في SupportAgent
            if ($agentId && $sentMessage && $sentMessage->message_id) {
                $agent = \App\Models\SupportAgent::find($agentId);

                if ($agent) {
                    // احذف القديمة أولاً
                    $this->deleteAgentJoinMessage($agent);

                    $agent->update([
                        'join_message_id' => $sentMessage->message_id,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SubscriptionService: sendMissingChannelsMessage failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  🗑️ حذف رسالة الداعم القديمة
    // ============================================================
    private function deleteAgentJoinMessage(\App\Models\SupportAgent $agent): void
    {
        if (! $agent->telegram_id || empty($agent->join_message_id)) {
            return;
        }

        try {
            $bot = app(Nutgram::class);

            $bot->deleteMessage(
                chat_id: $agent->telegram_id,
                message_id: (int) $agent->join_message_id,
            );

            $agent->update(['join_message_id' => null]);
        } catch (\Throwable $e) {
            $agent->update(['join_message_id' => null]);
        }
    }}
