<?php

namespace App\Telegram\Conversations\Admin\Support;

use App\Models\SupportAgent;
use App\Services\SupportChannelService;
use App\Telegram\Conversations\BaseConversation;
use App\Telegram\Screens\Admin\Support\SupportScreen;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class AddSupportAgentConversation extends BaseConversation
{
    protected ?string $name = null;

    public function start(Nutgram $bot): void
    {
        $this->askTracked(
            $bot,
            SupportScreen::askName(),
            parse_mode: 'HTML',
        );

        $this->next('receiveName');
    }

    public function receiveName(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '' || $text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if (mb_strlen($text) < 2) {
            $this->askTracked($bot, '⚠️ الاسم قصير جداً.');
            return;
        }

        $this->name = $text;

        // ✅ رسالة توضيحية: يقبل @username أو Telegram ID
        $this->askTracked(
            $bot,
            implode("\n", [
                '🆔 <b>معرّف الداعم</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📝 أرسل <b>معرّف الداعم</b> (أحد الخيارين):',
                '',
                '1️⃣ <b>@username</b> — مثال: <code>@ahmad_dev</code>',
                '2️⃣ <b>Telegram ID</b> — مثال: <code>8335709957</code>',
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '💡 <b>الأفضل:</b> @username',
                '   (يمكن الحصول عليه من بروفايل الداعم)',
                '',
                '↩️ أو /cancel للإلغاء',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('receiveUsername');
    }

    public function receiveUsername(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '' || $text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        // ✅ تحقق: هل هو رقم Telegram ID أم @username؟
        $isNumeric = ctype_digit($text);

        if ($isNumeric) {
            // ─── رقم Telegram ID ───
            $telegramId = (int) $text;
            $username   = '@' . $text;  // للعرض فقط

            // تحقق من التكرار
            if (SupportAgent::where('telegram_id', $telegramId)->exists()) {
                $this->askTracked($bot, '⚠️ هذا المعرّف مسجّل مسبقاً.');
                return;
            }
        } else {
            // ─── @username ───
            if (! str_starts_with($text, '@')) {
                $text = '@' . ltrim($text, '@');
            }

            $cleanUsername = ltrim($text, '@');

            if (strlen($cleanUsername) < 4) {
                $this->askTracked($bot, '⚠️ المعرّف قصير جداً (4 أحرف على الأقل).');
                return;
            }

            $username   = '@' . $cleanUsername;
            $telegramId = null;

            if (SupportAgent::where('username', $username)->exists()) {
                $this->askTracked($bot, '⚠️ المعرّف مسجّل مسبقاً.');
                return;
            }
        }

        // ─── إنشاء الداعم ───
        $agent = SupportAgent::create([
            'name'        => $this->name,
            'username'    => $username,
            'telegram_id' => $telegramId,
            'icon'        => '👨',
            'sort_order'  => SupportAgent::max('sort_order') + 1,
            'is_active'   => true,
        ]);

        // ─── إرسال رسالة الاشتراك ───
        $sent = false;

        if ($agent->hasTelegramId()) {
            $sent = app(SupportChannelService::class)->sendJoinMessage($agent);
        }

        // ─── عرض النتيجة ───
        $lines = [
            '✅ <b>تم إضافة الداعم</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📝 <b>الاسم:</b> ' . htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8'),
            '🆔 <b>المعرّف:</b> <code>' . $username . '</code>',
        ];

        if ($telegramId) {
            $lines[] = '📱 <b>Telegram ID:</b> <code>' . $telegramId . '</code>';
        }

        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━';
        $lines[] = '';

        if ($sent) {
            $lines[] = '📨 <i>تم إرسال رسالة الاشتراك له.</i>';
        } elseif ($telegramId) {
            $lines[] = '⏳ <i>لم يتمكن من الإرسال — قد يحتاج الداعم لبدء البوت.</i>';
        } else {
            $lines[] = '💡 <i>لم يُرسل شيء — أرسل للداعم أن يكتب:</i>';
            $lines[] = '<code>/support_start</code>';
        }

        $this->keep(
            $bot,
            implode("\n", $lines),
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '⬅️ رجوع للدعم',
                        callback_data: 'admin.support',
                    ),
                ),
        );

        $this->endAndClean($bot);
    }

    public function cancel(Nutgram $bot): void
    {
        $this->keep(
            $bot,
            '❌ تم الإلغاء.',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '⬅️ رجوع للدعم',
                        callback_data: 'admin.support',
                    ),
                ),
        );

        $this->endAndClean($bot);
    }
}
