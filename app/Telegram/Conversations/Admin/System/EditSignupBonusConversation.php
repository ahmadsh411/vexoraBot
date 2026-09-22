<?php

namespace App\Telegram\Conversations\Admin\System;

use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SignupBonusService;
use App\Telegram\Conversations\BaseConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class EditSignupBonusConversation extends BaseConversation
{
    protected ?string $currency = null;

    public function start(Nutgram $bot): void
    {
        $this->currency = Cache::pull("signup_bonus.edit.{$bot->userId()}");

        if (! in_array($this->currency, ['nsp', 'usd'], true)) {
            $this->keep($bot, '❌ انتهت صلاحية الجلسة.', reply_markup: $this->backKeyboard());
            $this->endAndClean($bot);
            return;
        }

        $label = $this->currency === 'nsp' ? 'NSP' : 'USD';
        $current = $this->currency === 'nsp'
            ? $this->getAmountNsp()
            : $this->getAmountUsd();

        $this->askTracked(
            $bot,
            implode("\n", [
                '✏️ <b>تعديل مبلغ مكافأة التسجيل</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '💱 <b>العملة:</b> ' . $label,
                '📌 <b>المبلغ الحالي:</b> <b>' . number_format($current, 2) . '</b>',
                '',
                '📤 أرسل المبلغ الجديد (رقم فقط):',
                '',
                '💡 أرسل <code>0</code> لتعطيل هذه العملة',
                '',
                '↩️ أو /cancel للإلغاء',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('receiveAmount');
    }

    public function receiveAmount(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel' || $text === '') {
            $this->cancel($bot);
            return;
        }

        if (! is_numeric($text)) {
            $this->askTracked($bot, '⚠️ أرسل رقماً صحيحاً:');
            return;
        }

        $amount = (float) $text;

        if ($amount < 0) {
            $this->askTracked($bot, '⚠️ المبلغ يجب أن يكون 0 أو أكثر:');
            return;
        }

        // احفظ
        $key = $this->currency === 'nsp'
            ? SignupBonusService::KEY_AMOUNT_NSP
            : SignupBonusService::KEY_AMOUNT_USD;

        $old = $this->currency === 'nsp'
            ? $this->getAmountNsp()
            : $this->getAmountUsd();

        Setting::set($key, (string) $amount);

        cache()->forget("setting.{$key}");

        Log::info('Signup bonus amount updated', [
            'admin_id' => $bot->userId(),
            'currency' => $this->currency,
            'old'      => $old,
            'new'      => $amount,
        ]);

        $label = $this->currency === 'nsp' ? 'NSP' : 'USD';

        // إشعار القناة
        $this->notifyChannel($bot, $label, $old, $amount);

        $this->keep(
            $bot,
            implode("\n", [
                '✅ <b>تم تحديث المبلغ</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '💱 <b>العملة:</b> ' . $label,
                '📌 <b>المبلغ القديم:</b> ' . number_format($old, 2),
                '✨ <b>المبلغ الجديد:</b> <b>' . number_format($amount, 2) . '</b>',
                '',
                $amount > 0
                    ? '💡 المكافأة ستُمنح للمستخدمين الجدد عند تفعيل المكافأة'
                    : '⚠️ هذه العملة <b>معطّلة</b> (المبلغ = 0)',
            ]),
            parse_mode: 'HTML',
            reply_markup: $this->backKeyboard(),
        );

        $this->endAndClean($bot);
    }

    // ═══════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════

    private function getAmountNsp(): float
    {
        return (float) Setting::get(SignupBonusService::KEY_AMOUNT_NSP, 0);
    }

    private function getAmountUsd(): float
    {
        return (float) Setting::get(SignupBonusService::KEY_AMOUNT_USD, 0);
    }

    private function notifyChannel(Nutgram $bot, string $label, float $old, float $new): void
    {
        try {
            $admin = User::where('telegram_id', $bot->userId())->first();

            $text = implode("\n", [
                '🎁 <b>تحديث مكافأة التسجيل</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '💱 <b>العملة:</b> ' . $label,
                '',
                '📌 <b>التغيير:</b>',
                '├── القديم: <code>' . number_format($old, 2) . '</code>',
                '└── الجديد: <b>' . number_format($new, 2) . '</b>',
                '',
                '👮 <b>بواسطة:</b> ' . ($admin?->username ?? 'الإدارة'),
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            app(NotificationService::class)->notifyGeneralChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about signup bonus amount', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function cancel(Nutgram $bot): void
    {
        $this->keep($bot, '❌ تم الإلغاء.', reply_markup: $this->backKeyboard());
        $this->endAndClean($bot);
    }

    private function backKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع لمكافأة التسجيل',
                    callback_data: 'admin.signup_bonus',
                ),
            );
    }
}
