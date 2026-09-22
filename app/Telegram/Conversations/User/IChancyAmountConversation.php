<?php

namespace App\Telegram\Conversations\User;

use App\Models\Setting;
use App\Models\User;
use App\Services\IChancy\IChancyAccountService;
use App\Telegram\Conversations\BaseConversation;
use App\Telegram\Keyboards\User\IChancyDepositKeyboard;
use App\Telegram\Keyboards\User\IChancyWithdrawKeyboard;
use App\Telegram\Screens\User\IChancyDepositScreen;
use App\Telegram\Screens\User\IChancyWithdrawScreen;
use Illuminate\Support\Facades\Cache;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class IChancyAmountConversation extends BaseConversation
{
    protected string $type = 'deposit';

    public function start(Nutgram $bot): void
    {
        $userId = $bot->userId();
        $type = Cache::pull("ichancy_awaiting_type_{$userId}") ?? 'deposit';

        $this->type = $type;

        $user = User::where('telegram_id', $userId)->first();

        if (! $user) {
            $this->keep(
                $bot,
                '❌ تعذر التعرف على حسابك.',
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        if ($this->type === 'deposit') {
            $this->askTracked(
                $bot,
                IChancyDepositScreen::askAmount($user),
                parse_mode: 'HTML',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '↩️ رجوع',
                            callback_data: 'user.ichancy.deposit',
                        ),
                    ),
            );
        } else {
            $balanceData = app(IChancyAccountService::class)->getBalanceForUser($user, 0);
            $balance = $balanceData ? (float) $balanceData['balance'] : 0;

            $this->askTracked(
                $bot,
                IChancyWithdrawScreen::askAmount($user, $balance),
                parse_mode: 'HTML',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '↩️ رجوع',
                            callback_data: 'user.ichancy.withdraw',
                        ),
                    ),
            );
        }

        $this->next('receiveAmount');
    }

    public function receiveAmount(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->keep(
                $bot,
                '❌ تم الإلغاء.',
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        if (! is_numeric($text)) {
            $this->askTracked($bot, '⚠️ أرسل رقماً صحيحاً:');
            return;
        }

        $amount = (float) $text;

        if ($amount <= 0) {
            $this->askTracked($bot, '⚠️ المبلغ يجب أن يكون أكبر من 0.');
            return;
        }

        $user = User::where('telegram_id', $bot->userId())->first();

        if (! $user) {
            $this->keep(
                $bot,
                '❌ تعذر التعرف على حسابك.',
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        $cfg = [
            'min_deposit'  => (int) Setting::get('ichancy.min_deposit', 100),
            'max_deposit'  => (int) Setting::get('ichancy.max_deposit', 1000000),
            'min_withdraw' => (int) Setting::get('ichancy.min_withdraw', 100),
            'max_withdraw' => (int) Setting::get('ichancy.max_withdraw', 1000000),
            'currency'     => Setting::get('ichancy.currency', 'NSP'),
        ];

        if ($this->type === 'deposit') {
            if ($amount < $cfg['min_deposit']) {
                $this->askTracked($bot, '⚠️ الحد الأدنى: ' . number_format($cfg['min_deposit']) . ' ' . $cfg['currency']);
                return;
            }
            if ($amount > $cfg['max_deposit']) {
                $this->askTracked($bot, '⚠️ الحد الأقصى: ' . number_format($cfg['max_deposit']) . ' ' . $cfg['currency']);
                return;
            }
            $wallet = $user->wallet;
            $balance = $wallet ? (float) $wallet->balance_nsp : 0;
            if ($amount > $balance) {
                $this->askTracked($bot, '⚠️ رصيد المحفظة غير كافٍ. المتاح: ' . number_format($balance, 2) . ' ' . $cfg['currency']);
                return;
            }

            Cache::put("ichancy_deposit_amount_{$user->id}", $amount, now()->addMinutes(10));

            // ✅ تأكيد نهائي — يبقى + زر رجوع
            $this->keep(
                $bot,
                IChancyDepositScreen::confirm($user, $amount),
                parse_mode: 'HTML',
                reply_markup: IChancyDepositKeyboard::confirm(),
            );
        } else {
            if ($amount < $cfg['min_withdraw']) {
                $this->askTracked($bot, '⚠️ الحد الأدنى: ' . number_format($cfg['min_withdraw']) . ' ' . $cfg['currency']);
                return;
            }
            if ($amount > $cfg['max_withdraw']) {
                $this->askTracked($bot, '⚠️ الحد الأقصى: ' . number_format($cfg['max_withdraw']) . ' ' . $cfg['currency']);
                return;
            }

            $balanceData = app(IChancyAccountService::class)->getBalanceForUser($user, 0);
            $ichancyBalance = $balanceData ? (float) $balanceData['balance'] : 0;
            if ($amount > $ichancyBalance) {
                $this->askTracked($bot, '⚠️ رصيد IChancy غير كافٍ. المتاح: ' . number_format($ichancyBalance, 2) . ' ' . $cfg['currency']);
                return;
            }

            Cache::put("ichancy_withdraw_amount_{$user->id}", $amount, now()->addMinutes(10));

            // ✅ تأكيد نهائي — يبقى + زر رجوع
            $this->keep(
                $bot,
                IChancyWithdrawScreen::confirm($user, $amount),
                parse_mode: 'HTML',
                reply_markup: IChancyWithdrawKeyboard::confirm(),
            );
        }

        $this->endAndClean($bot);
    }

    /**
     * 🔙 زر الرجوع الافتراضي
     */
    private function backKeyboard(): InlineKeyboardMarkup
    {
        $callback = $this->type === 'deposit'
            ? 'user.ichancy.deposit'
            : 'user.ichancy.withdraw';

        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: $callback,
                ),
            );
    }
}
