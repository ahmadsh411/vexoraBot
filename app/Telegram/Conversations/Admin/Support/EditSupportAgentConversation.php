<?php

namespace App\Telegram\Conversations\Admin\Support;

use App\Models\SupportAgent;
use App\Telegram\Conversations\BaseConversation;
use Illuminate\Support\Facades\Cache;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class EditSupportAgentConversation extends BaseConversation
{
    protected ?int $agentId = null;
    protected ?string $field = null;

    public function start(Nutgram $bot): void
    {
        $userId = $bot->userId();

        $this->agentId = Cache::pull("support.edit.agent.{$userId}");
        $this->field   = Cache::pull("support.edit.field.{$userId}");

        if (! $this->agentId || ! $this->field) {
            $this->keep(
                $bot,
                '❌ انتهت صلاحية الجلسة.',
                reply_markup: $this->backKeyboard(null),
            );
            $this->endAndClean($bot);
            return;
        }

        $agent = SupportAgent::find($this->agentId);

        if (! $agent) {
            $this->keep(
                $bot,
                '❌ الداعم غير موجود.',
                reply_markup: $this->backKeyboard(null),
            );
            $this->endAndClean($bot);
            return;
        }

        if ($this->field === 'name') {
            // ─── تعديل الاسم ───
            $this->askTracked(
                $bot,
                implode("\n", [
                    '✏️ <b>تعديل الاسم</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '📝 <b>الاسم الحالي:</b>',
                    '<code>' . htmlspecialchars($agent->name, ENT_QUOTES, 'UTF-8') . '</code>',
                    '',
                    '📤 <b>أرسل الاسم الجديد:</b>',
                    '',
                    '⚠️ 2 أحرف على الأقل',
                    '',
                    '↩️ أو /cancel للإلغاء.',
                ]),
                parse_mode: 'HTML',
            );
            $this->next('receiveName');
        } else {
            // ─── تعديل المعرّف ───
            $currentId = $agent->telegram_id
                ? 'Telegram ID: <code>' . $agent->telegram_id . '</code>'
                : '';

            $this->askTracked(
                $bot,
                implode("\n", [
                    '🆔 <b>تعديل المعرّف</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '📝 <b>المعرّف الحالي:</b>',
                    '<code>' . htmlspecialchars($agent->username, ENT_QUOTES, 'UTF-8') . '</code>',
                    $currentId,
                    '',
                    '📤 <b>أرسل المعرّف الجديد:</b>',
                    '',
                    '1️⃣ <b>@username</b> — مثال: <code>@ahmad_dev</code>',
                    '2️⃣ <b>Telegram ID</b> — مثال: <code>8335709957</code>',
                    '',
                    '⚠️ @username: 4 أحرف على الأقل',
                    '',
                    '↩️ أو /cancel للإلغاء.',
                ]),
                parse_mode: 'HTML',
            );
            $this->next('receiveUsername');
        }
    }

    // ============================================================
    //  استقبال الاسم
    // ============================================================
    public function receiveName(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel' || $text === '') {
            $this->cancel($bot);
            return;
        }

        if (mb_strlen($text) < 2) {
            $this->askTracked($bot, '⚠️ الاسم قصير جداً. حاول مرة أخرى:');
            return;
        }

        $agent = SupportAgent::find($this->agentId);

        if (! $agent) {
            $this->cancel($bot);
            return;
        }

        $agent->update(['name' => $text]);
        $agent->refresh();

        $this->keep(
            $bot,
            implode("\n", [
                '✅ <b>تم تحديث الاسم</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👤 <b>الاسم الجديد:</b> ' . htmlspecialchars($agent->full_name, ENT_QUOTES, 'UTF-8'),
            ]),
            parse_mode: 'HTML',
            reply_markup: $this->backKeyboard($agent->id),
        );

        $this->endAndClean($bot);
    }

    // ============================================================
    //  استقبال المعرّف
    // ============================================================
    public function receiveUsername(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel' || $text === '') {
            $this->cancel($bot);
            return;
        }

        // ✅ تحقق: هل هو رقم أم @username؟
        $isNumeric = ctype_digit($text);

        if ($isNumeric) {
            // ─── Telegram ID ───
            $telegramId = (int) $text;
            $username   = '@' . $text;

            $exists = SupportAgent::where('telegram_id', $telegramId)
                ->where('id', '!=', $this->agentId)
                ->exists();

            if ($exists) {
                $this->askTracked($bot, '⚠️ هذا المعرّف مسجّل لداعم آخر.');
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

            $exists = SupportAgent::where('username', $username)
                ->where('id', '!=', $this->agentId)
                ->exists();

            if ($exists) {
                $this->askTracked($bot, '⚠️ المعرّف مسجّل لداعم آخر.');
                return;
            }
        }

        $agent = SupportAgent::find($this->agentId);

        if (! $agent) {
            $this->cancel($bot);
            return;
        }

        // ─── تحديث ───
        $updateData = ['username' => $username];

        if ($telegramId) {
            $updateData['telegram_id'] = $telegramId;
        }

        $agent->update($updateData);
        $agent->refresh();

        // ─── عرض ───
        $lines = [
            '✅ <b>تم تحديث المعرّف</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🆔 <b>المعرّف:</b> <code>' . htmlspecialchars($agent->username, ENT_QUOTES, 'UTF-8') . '</code>',
        ];

        if ($agent->telegram_id) {
            $lines[] = '📱 <b>Telegram ID:</b> <code>' . $agent->telegram_id . '</code>';
        }

        $this->keep(
            $bot,
            implode("\n", $lines),
            parse_mode: 'HTML',
            reply_markup: $this->backKeyboard($agent->id),
        );

        $this->endAndClean($bot);
    }

    // ============================================================
    //  إلغاء
    // ============================================================
    private function cancel(Nutgram $bot): void
    {
        $this->keep(
            $bot,
            '❌ تم الإلغاء.',
            reply_markup: $this->backKeyboard($this->agentId),
        );

        $this->endAndClean($bot);
    }

    /**
     * 🔙 زر الرجوع الموحّد
     */
    private function backKeyboard(?int $agentId): InlineKeyboardMarkup
    {
        $button = $agentId
            ? InlineKeyboardButton::make(
                text: '↩️ رجوع لتفاصيل الداعم',
                callback_data: 'support.agent.show.' . $agentId,
            )
            : InlineKeyboardButton::make(
                text: '↩️ رجوع للدعم',
                callback_data: 'admin.support',
            );

        return InlineKeyboardMarkup::make()->addRow($button);
    }
}
