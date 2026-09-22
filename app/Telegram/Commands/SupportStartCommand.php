<?php

namespace App\Telegram\Commands;

use App\Models\SupportAgent;
use App\Services\SupportChannelService;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

class SupportStartCommand extends Command
{
    protected string $command = 'support_start';
    protected ?string $description = 'تسجيل الداعم';

    public function handle(Nutgram $bot): void
    {
        $telegramId = $bot->userId();
        $telegramUser = $bot->user();
        $telegramUsername = $telegramUser?->username;

        // ─── 1. ابحث بـ telegram_id أولاً ───
        $agent = SupportAgent::where('telegram_id', $telegramId)->first();

        // ─── 2. إذا لم يوجد — ابحث بـ @username ───
        if (! $agent && ! empty($telegramUsername)) {
            $agent = SupportAgent::where('username', '@' . $telegramUsername)->first();

            if (! $agent) {
                $agent = SupportAgent::where('username', $telegramUsername)->first();
            }
        }

        // ─── 3. إذا لم يوجد — ابحث في حقل username كرقم ───
        if (! $agent) {
            $agent = SupportAgent::where('username', (string) $telegramId)->first();

            if (! $agent) {
                $agent = SupportAgent::where('username', '@' . $telegramId)->first();
            }
        }

        // ─── 4. إذا لم يوجد ───
        if (! $agent) {
            $bot->sendMessage(
                text: implode("\n", [
                    '❌ <b>غير مسجل كداعم</b>',
                    '',
                    'تواصل مع الإدارة لإضافتك.',
                    '',
                    '🆔 <b>معرفك:</b> <code>' . $telegramId . '</code>',
                    '📛 <b>اسم المستخدم:</b> <code>' . ($telegramUsername ? '@' . $telegramUsername : 'لا يوجد') . '</code>',
                ]),
                parse_mode: 'HTML',
            );
            return;
        }

        // ─── 5. احفظ telegram_id ───
        $agent->update(['telegram_id' => $telegramId]);

        // ─── 6. أرسل رسالة الاشتراك ───
        $sent = app(SupportChannelService::class)->sendJoinMessage($agent);

        if ($sent) {
            $bot->sendMessage(
                text: implode("\n", [
                    '✅ <b>تم تسجيلك كداعم</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '📨 تحقق من رسالة الاشتراك في القنوات.',
                ]),
                parse_mode: 'HTML',
            );
        } else {
            $bot->sendMessage(
                text: '⚠️ تم تسجيلك، لكن تعذّر إرسال روابط القنوات. تواصل مع الإدارة.',
                parse_mode: 'HTML',
            );
        }
    }
}
