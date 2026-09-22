<?php

namespace App\Telegram\Conversations;

use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ChooseReferralTypeConversation extends BaseConversation
{
    public function start(Nutgram $bot): void
    {
        $user = User::where('telegram_id', $bot->userId())->first();

        if (! $user) {
            $this->keep($bot, '❌ تعذر التعرف على حسابك.', reply_markup: $this->backKeyboard());
            $this->endAndClean($bot);
            return;
        }

        if ($user->hasChosenReferralType()) {
            $this->keep($bot, '⚠️ لقد اخترت نوع الإحالة مسبقاً.', reply_markup: $this->backKeyboard());
            $this->endAndClean($bot);
            return;
        }

        // 🗑️ سؤال — يُحذف
        $this->askTracked(
            $bot,
            implode("\n", [
                '🎯 <b>اختيار نوع الإحالة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '⚡ <b>الفوري</b>',
                '• تربح نسبة من كل إيداع مباشرة',
                '• L1: 5% من الإيداع',
                '• L2: 2% من الإيداع',
                '• مكاسب فورية لكل إيداع',
                '',
                '📅 <b>الدوري</b>',
                '• تربح نسبة من "الحرق" كل 10 أيام',
                '• L1: 10% من الحرق',
                '• L2: 3% من الحرق',
                '• مكاسب أكبر لكن متأخرة',
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '⚠️ <b>لا يمكن تغيير النوع لاحقاً.</b>',
                '',
                'اختر بعناية:',
            ]),
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '⚡ فوري (نسبة من كل إيداع)',
                        callback_data: 'ref.type.instant',
                    ),
                )
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '📅 دوري (نسبة من الحرق كل 10 أيام)',
                        callback_data: 'ref.type.cycle',
                    ),
                ),
        );

        $this->next('handleChoice');
    }

    public function handleChoice(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;

        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        if (! in_array($data, ['ref.type.instant', 'ref.type.cycle'], true)) {
            return;
        }

        $type = $data === 'ref.type.instant'
            ? Referral::TYPE_INSTANT
            : Referral::TYPE_CYCLE;

        $user = User::where('telegram_id', $bot->userId())->first();

        if (! $user) {
            $this->keep($bot, '❌ تعذر التعرف على حسابك.', reply_markup: $this->backKeyboard());
            $this->endAndClean($bot);
            return;
        }

        try {
            $success = app(ReferralService::class)->chooseReferralType($user, $type);

            if (! $success) {
                $this->keep(
                    $bot,
                    '⚠️ فشل الاختيار. ربما اخترت مسبقاً.',
                    reply_markup: $this->backKeyboard(),
                );
                $this->endAndClean($bot);
                return;
            }

            $user->refresh();

            $label = $type === Referral::TYPE_INSTANT ? '⚡ فوري' : '📅 دوري';
            $link  = $user->referral_link;

            // ✅ نجاح — يبقى + زر الرجوع للوحة
            $this->keep(
                $bot,
                implode("\n", [
                    '✅ <b>تم اختيار النوع</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🎯 <b>النوع:</b> ' . $label,
                    '',
                    '🔗 <b>رابط الإحالة:</b>',
                    '<code>' . htmlspecialchars($link ?? '—', ENT_QUOTES, 'UTF-8') . '</code>',
                    '',
                    '💡 شارك الرابط مع أصدقائك لتبدأ الربح!',
                ]),
                parse_mode: 'HTML',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '🏠 رجوع للوحة',
                            callback_data: 'user.dashboard',
                        ),
                    ),
            );
        } catch (\Throwable $e) {
            Log::error('Choose referral type failed', [
                'user_id' => $user->id,
                'type'    => $type,
                'error'   => $e->getMessage(),
            ]);

            $this->keep(
                $bot,
                '❌ خطأ: ' . $e->getMessage(),
                reply_markup: $this->backKeyboard(),
            );
        }

        $this->endAndClean($bot);
    }

    /**
     * 🔙 زر الرجوع الافتراضي
     */
    private function backKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🏠 رجوع للوحة',
                    callback_data: 'user.dashboard',
                ),
            );
    }
}
