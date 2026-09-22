<?php

namespace App\Telegram\Conversations\User;

use App\Models\User;
use App\Services\IChancy\IChancyService;
use App\Telegram\Conversations\BaseConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class DeleteAccountConversation extends BaseConversation
{
    public function start(Nutgram $bot): void
    {
        $user = $this->getUser($bot);

        if (! $user) {
            $this->keep(
                $bot,
                '❌ تعذر التعرف على حسابك.',
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        // ─── فحص الرصيد ───
        $wallet = $user->wallet;
        $balanceNsp = $wallet ? (float) $wallet->balance_nsp : 0;

        if ($balanceNsp > 0) {
            $bot->editMessageText(
                text: implode("\n", [
                    '',
                    '⚠️ <b>لا يمكن حذف الحساب</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '💰 <b>لديك رصيد في المحفظة:</b>',
                    '└── <code>' . number_format($balanceNsp, 2) . ' NSP</code>',
                    '',
                    '📝 <b>يجب سحب الرصيد أولاً</b>',
                    '',
                    '💡 اسحب رصيدك ثم عد للحذف.',
                    '',
                ]),
                parse_mode: 'HTML',
                reply_markup: InlineKeyboardMarkup::make()
                    // ─── 💰 سحب الرصيد ───
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '💰 سحب الرصيد',
                            callback_data: 'user.withdraw',
                            style: ButtonStyle::SUCCESS,
                        ),
                    )
                    // ─── ↩️ رجوع (بدون لون) ───
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '↩️ رجوع',
                            callback_data: 'user.dashboard',
                        ),
                    ),
            );

            $this->endAndClean($bot);
            return;
        }

        // ─── فحص المعاملات المعلقة ───
        $pending = \App\Models\Transaction::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->count();

        if ($pending > 0) {
            $bot->editMessageText(
                text: implode("\n", [
                    '',
                    '⚠️ <b>لا يمكن حذف الحساب</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '⏳ <b>لديك معاملات معلقة:</b>',
                    '└── <code>' . $pending . '</code>',
                    '',
                    '📝 <b>انتظر انتهاء المعاملات</b>',
                    '',
                    '💡 عاود المحاولة لاحقاً.',
                    '',
                ]),
                parse_mode: 'HTML',
                reply_markup: InlineKeyboardMarkup::make()
                    // ─── ↩️ رجوع (بدون لون) ───
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '↩️ رجوع',
                            callback_data: 'user.dashboard',
                        ),
                    ),
            );

            $this->endAndClean($bot);
            return;
        }

        // ─── عرض التحذير ───
        $bot->editMessageText(
            text: implode("\n", [
                '',
                '🗑 <b>حذف الحساب</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '⚠️ <b>تحذير شديد!</b>',
                '',
                '🚨 <b>سيتم حذف:</b>',
                '├── 👤 حسابك في البوت',
                '├── 🎮 حسابك في IChancy',
                '├── 💰 رصيدك (إن وُجد)',
                '├── 🤝 إحالاتك',
                '└── 📊 سجل عملياتك',
                '',
                '❌ <b>لا يمكن التراجع عن هذا الإجراء!</b>',
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '💬 <i>للمتابعة، أرسل كلمة:</i>',
                '<code>حذف نهائي</code>',
                '',
                '<i>أو /cancel للإلغاء</i>',
                '',
            ]),
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()
                // ─── ❌ إلغاء (بدون لون) ───
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '❌ إلغاء',
                        callback_data: 'user.dashboard',
                    ),
                ),
        );

        $this->next('confirmDeletion');
    }

    public function confirmDeletion(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel' || $text === 'الغاء' || $text === 'إلغاء') {
            $this->keep(
                $bot,
                '✅ تم إلغاء الحذف.',
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        if ($text !== 'حذف نهائي') {
            $this->askTracked(
                $bot,
                "⚠️ أرسل بالضبط: <code>حذف نهائي</code>\n\nأو /cancel للإلغاء.",
                parse_mode: 'HTML',
            );
            return;
        }

        $user = $this->getUser($bot);

        if (! $user) {
            $this->keep(
                $bot,
                '❌ تعذر التعرف على حسابك.',
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        $this->askTracked(
            $bot,
            implode("\n", [
                '',
                '⏳ <b>جاري حذف حسابك...</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🗑 حذف من البوت',
                '🎮 حذف من IChancy',
                '',
                '⚡ الرجاء الانتظار...',
                '',
            ]),
            parse_mode: 'HTML',
        );

        try {
            $ichancyDeleted = false;

            if ($user->ichancyAccount && $user->ichancyAccount->ichancy_player_id) {
                try {
                    $ichancy = app(IChancyService::class);
                    $result  = $ichancy->deletePlayer($user->ichancyAccount->ichancy_player_id);

                    if ($result && ($result['status'] ?? false)) {
                        $ichancyDeleted = true;
                        Log::info('User deleted from IChancy', [
                            'user_id'   => $user->id,
                            'player_id' => $user->ichancyAccount->ichancy_player_id,
                        ]);
                    } else {
                        Log::warning('Failed to delete from IChancy', [
                            'user_id' => $user->id,
                            'result'  => $result,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::error('IChancy delete exception', [
                        'user_id' => $user->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            $userId     = $user->id;
            $username   = $user->username;
            $telegramId = $user->telegram_id;

            DB::transaction(function () use ($user) {
                $user->forceDelete();
            });

            Log::info('User account deleted permanently', [
                'user_id'         => $userId,
                'username'        => $username,
                'telegram_id'     => $telegramId,
                'ichancy_deleted' => $ichancyDeleted,
            ]);

            $this->keep(
                $bot,
                implode("\n", [
                    '',
                    '✅ <b>تم حذف حسابك نهائياً</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '👤 <b>حساب البوت:</b>',
                    '└── ✅ محذوف',
                    '',
                    '🎮 <b>حساب IChancy:</b>',
                    '└── ' . ($ichancyDeleted ? '✅ محذوف' : '⚠️ لم يُحذف'),
                    '',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '💚 <b>شكراً لاستخدامك البوت</b>',
                    '',
                    '📞 إذا أردت العودة:',
                    '└── استخدم /start للتسجيل من جديد',
                    '',
                ]),
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            Log::error('Account deletion failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            $this->keep(
                $bot,
                implode("\n", [
                    '',
                    '❌ <b>فشل الحذف</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '📝 <b>السبب:</b>',
                    '<i>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</i>',
                    '',
                    '💬 تواصل مع الدعم للمساعدة.',
                    '',
                ]),
                parse_mode: 'HTML',
            );
        }

        $this->endAndClean($bot);
    }

    private function getUser(Nutgram $bot): ?User
    {
        return User::where('telegram_id', $bot->userId())->first();
    }

    // ─── 🔙 زر الرجوع (بدون لون) ───
    private function backKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع للوحة',
                    callback_data: 'user.dashboard',
                ),
            );
    }
}
