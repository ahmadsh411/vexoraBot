<?php

namespace App\Telegram\Conversations\Admin\Users;

use App\Models\User;
use App\Telegram\Conversations\BaseConversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class SearchUserConversation extends BaseConversation
{
    public function start(Nutgram $bot): void
    {
        // 🗑️ سؤال — يُحذف عند النهاية
        $this->askTracked(
            $bot,
            implode("\n", [
                '🔎 <b>البحث عن مستخدم</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                'أرسل أحد ما يلي:',
                '',
                '• <b>اسم المستخدم</b> (مثال: <code>Vexora_ahmad</code>)',
                '• <b>المعرف</b> (مثال: <code>2</code>)',
                '• <b>Telegram ID</b> (مثال: <code>8335709957</code>)',
                '',
                'أو /cancel للإلغاء.',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('search');
    }

    public function search(Nutgram $bot): void
    {
        $query = trim((string) ($bot->message()->text ?? ''));

        if ($query === '' || $query === '/cancel') {
            // ✅ إلغاء — يبقى + زر رجوع
            $this->keep(
                $bot,
                '❌ تم إلغاء البحث.',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '⬅️ رجوع للمستخدمين',
                            callback_data: 'admin.users',
                        ),
                    ),
            );
            $this->endAndClean($bot);
            return;
        }

        $user = $this->findUser($query);

        if (! $user) {
            // 🗑️ خطأ إدخال — يُحذف (يعيد المحاولة)
            $this->askTracked(
                $bot,
                implode("\n", [
                    '❌ <b>لم يتم العثور على مستخدم.</b>',
                    '',
                    '🔎 أرسل استعلاماً آخر (أو /cancel):',
                ]),
                parse_mode: 'HTML',
            );
            return;
        }

        $this->showResult($bot, $user);
    }

    private function findUser(string $query): ?User
    {
        if (ctype_digit($query)) {
            $user = User::find((int) $query);
            if ($user) {
                return $user;
            }

            return User::where('telegram_id', (int) $query)->first();
        }

        return User::where('username', 'LIKE', '%' . $query . '%')->first();
    }

    private function showResult(Nutgram $bot, User $user): void
    {
        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        if ($name === '') {
            $name = $user->username;
        }

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '👁️ عرض التفاصيل',
                    callback_data: "admin.users.show.{$user->id}",
                        style: ButtonStyle::PRIMARY,
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔎 بحث آخر',
                    callback_data: 'admin.users.search',
                        style: ButtonStyle::PRIMARY,
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: 'admin.users',
                ),
            );

        // ✅ نجاح — يبقى مع أزراره
        $this->keep(
            $bot,
            implode("\n", [
                '✅ <b>تم العثور على المستخدم</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👤 <b>الاسم:</b> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                '🆔 <b>المعرف:</b> <code>' . $user->id . '</code>',
                '📛 <b>اسم المستخدم:</b> <code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
                '📱 <b>Telegram ID:</b> <code>' . $user->telegram_id . '</code>',
                '🟢 <b>الحالة:</b> ' . ($user->is_active ? 'نشط' : 'موقوف'),
            ]),
            parse_mode: 'HTML',
            reply_markup: $keyboard,
        );

        $this->endAndClean($bot);
    }
}
