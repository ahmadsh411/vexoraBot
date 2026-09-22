<?php

namespace App\Telegram\Conversations\Admin\Users;

use App\Models\User;
use App\Services\NotificationService;
use App\Services\UserService;
use App\Telegram\Conversations\BaseConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class EditUserConversation extends BaseConversation
{
    public function start(Nutgram $bot): void
    {
        $cacheKey = 'edit_user_target_' . $bot->userId() . '_' . $bot->chatId();
        $targetUserId = Cache::pull($cacheKey);

        if ($targetUserId === null) {
            $this->keep(
                $bot,
                '❌ انتهت صلاحية الجلسة.',
                reply_markup: $this->backKeyboard($targetUserId),
            );
            $this->endAndClean($bot);
            return;
        }

        $stateKey = $this->stateKey($bot);

        Cache::put($stateKey, [
            'target_user_id' => (int) $targetUserId,
            'first_name'     => null,
            'last_name'      => null,
        ], now()->addMinutes(30));

        $user = User::withTrashed()->find((int) $targetUserId);

        if (! $user || $user->trashed()) {
            $this->keep(
                $bot,
                '❌ المستخدم غير متاح.',
                reply_markup: $this->backKeyboard($targetUserId),
            );
            $this->endAndClean($bot);
            return;
        }

        // 🗑️ سؤال — يُحذف عند النهاية
        $this->askTracked(
            $bot,
            implode("\n", [
                '✏️ <b>تعديل المستخدم</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👤 <b>الاسم الحالي:</b> ' . htmlspecialchars($user->first_name ?? '-', ENT_QUOTES, 'UTF-8'),
                '📛 <b>اسم المستخدم:</b> <code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
                '',
                '📝 أرسل <b>الاسم الأول</b> الجديد:',
                '',
                '↩️ أو /skip للتخطي.',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('askFirstName');
    }

    public function askFirstName(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text !== '' && ! str_starts_with($text, '/')) {
            $state = $this->getState($bot);
            $state['first_name'] = $text;
            $this->putState($bot, $state);
        }

        // 🗑️ سؤال — يُحذف
        $this->askTracked(
            $bot,
            '📝 أرسل <b>الاسم الأخير</b> (أو /skip):',
            parse_mode: 'HTML',
        );

        $this->next('askLastName');
    }

    public function askLastName(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text !== '' && ! str_starts_with($text, '/')) {
            $state = $this->getState($bot);
            $state['last_name'] = $text;
            $this->putState($bot, $state);
        }

        $this->apply($bot);
    }

    private function apply(Nutgram $bot): void
    {
        $state = $this->getState($bot);

        $targetUserId = $state['target_user_id'] ?? null;
        $firstName = $state['first_name'] ?? null;
        $lastName = $state['last_name'] ?? null;

        if (! $targetUserId) {
            $this->keep(
                $bot,
                '❌ انتهت الجلسة.',
                reply_markup: $this->backKeyboard(null),
            );
            $this->clearState($bot);
            $this->endAndClean($bot);
            return;
        }

        $user = User::find((int) $targetUserId);

        if (! $user) {
            $this->keep(
                $bot,
                '❌ المستخدم غير موجود.',
                reply_markup: $this->backKeyboard(null),
            );
            $this->clearState($bot);
            $this->endAndClean($bot);
            return;
        }

        $data = [];
        $changedFields = [];

        if ($firstName !== null) {
            $data['first_name'] = $firstName;
            $changedFields['first_name'] = ['old' => $user->first_name, 'new' => $firstName];
        }

        if ($lastName !== null) {
            $data['last_name'] = $lastName;
            $changedFields['last_name'] = ['old' => $user->last_name, 'new' => $lastName];
        }

        if (! empty($data)) {
            app(UserService::class)->update($user, $data);
            $user->refresh();

            try {
                \App\Models\AdminAction::log(
                    adminId: $bot->userId(),
                    action: 'user.edit',
                    targetType: User::class,
                    targetId: $user->id,
                    changes: $changedFields,
                );
            } catch (\Throwable $e) {
            }

            // ✅ إشعار القناة (لمستخدم آخر — لا يُحذف)
            $this->notifyChannel($bot, $user, $changedFields);
        }

        // ✅ نجاح — يبقى + زر رجوع
        $this->keep(
            $bot,
            implode("\n", [
                '✅ <b>تم التحديث</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👤 <b>الاسم:</b> ' . htmlspecialchars($user->display_name, ENT_QUOTES, 'UTF-8'),
            ]),
            parse_mode: 'HTML',
            reply_markup: $this->backKeyboard($user->id),
        );

        $this->clearState($bot);
        $this->endAndClean($bot);
    }

    private function notifyChannel(Nutgram $bot, User $user, array $changes): void
    {
        try {
            $lines = [
                '✏️ <b>تم تعديل مستخدم</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👤 ' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8'),
                '🆔 <code>' . $user->id . '</code>',
                '',
                '📝 <b>التغييرات:</b>',
            ];

            foreach ($changes as $field => $change) {
                $label = $field === 'first_name' ? 'الاسم الأول' : 'الاسم الأخير';
                $lines[] = "• {$label}: " . ($change['old'] ?? '—') . " → " . ($change['new'] ?? '—');
            }

            $lines[] = '';
            $lines[] = '👮 بواسطة: <code>' . $bot->userId() . '</code>';

            app(NotificationService::class)->notifyUsersChannel($bot, implode("\n", $lines));
        } catch (\Throwable $e) {
        }
    }

    /**
     * 🔙 زر الرجوع — يعود لتفاصيل المستخدم إن وُجد، وإلا لقائمة المستخدمين
     */
    private function backKeyboard(?int $userId): InlineKeyboardMarkup
    {
        $button = $userId
            ? InlineKeyboardButton::make(
                text: '⬅️ رجوع لتفاصيل المستخدم',
                callback_data: "admin.users.show.{$userId}",
            )
            : InlineKeyboardButton::make(
                text: '⬅️ رجوع للمستخدمين',
                callback_data: 'admin.users',
            );

        return InlineKeyboardMarkup::make()->addRow($button);
    }

    private function stateKey(Nutgram $bot): string
    {
        return 'edit_user_state_' . $bot->userId() . '_' . $bot->chatId();
    }

    private function getState(Nutgram $bot): array
    {
        return Cache::get($this->stateKey($bot), []);
    }

    private function putState(Nutgram $bot, array $state): void
    {
        Cache::put($this->stateKey($bot), $state, now()->addMinutes(30));
    }

    private function clearState(Nutgram $bot): void
    {
        Cache::forget($this->stateKey($bot));
    }
}
