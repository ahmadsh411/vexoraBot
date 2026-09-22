<?php

namespace App\Telegram\Conversations\Admin\System;

use App\Models\Setting;
use App\Models\User;
use App\Services\DepositBonusService;
use App\Services\NotificationService;
use App\Telegram\Conversations\BaseConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class EditDepositBonusConversation extends BaseConversation
{
    public function start(Nutgram $bot): void
    {
        $field = Cache::pull("deposit_bonus.edit.{$bot->userId()}");

        if ($field !== 'percent') {
            $this->keep($bot, '❌ انتهت صلاحية الجلسة.', reply_markup: $this->backKeyboard());
            $this->endAndClean($bot);
            return;
        }

        $current = (int) Setting::get(DepositBonusService::KEY_PERCENT, 10);

        $this->askTracked(
            $bot,
            implode("\n", [
                '📊 <b>تعديل نسبة المكافأة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📌 <b>النسبة الحالية:</b> <b>' . $current . '%</b>',
                '',
                '📤 أرسل النسبة الجديدة (0-100):',
                '',
                '💡 مثال: <code>15</code> = 15%',
                '',
                '↩️ أو /cancel للإلغاء',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('receivePercent');
    }

    public function receivePercent(Nutgram $bot): void
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

        $percent = (int) $text;

        if ($percent < 0 || $percent > 100) {
            $this->askTracked($bot, '⚠️ النسبة بين 0 و 100:');
            return;
        }

        $old = (int) Setting::get(DepositBonusService::KEY_PERCENT, 10);

        Setting::set(DepositBonusService::KEY_PERCENT, (string) $percent);

        Log::info('Deposit bonus percent updated', [
            'admin_id' => $bot->userId(),
            'old'      => $old,
            'new'      => $percent,
        ]);

        // ✅ إشعار القناة
        $this->notifyChannel($bot, $old, $percent);

        $this->keep(
            $bot,
            implode("\n", [
                '✅ <b>تم تحديث النسبة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📊 <b>النسبة الجديدة:</b> <b>' . $percent . '%</b>',
                '',
                '💡 مثال: إيداع 1000 ل.س →',
                '└── مكافأة: <b>' . number_format(1000 * $percent / 100, 2) . ' ل.س</b>',
            ]),
            parse_mode: 'HTML',
            reply_markup: $this->backKeyboard(),
        );

        $this->endAndClean($bot);
    }

    private function notifyChannel(Nutgram $bot, int $old, int $new): void
    {
        try {
            $admin = User::where('telegram_id', $bot->userId())->first();

            $text = implode("\n", [
                '🎁 <b>تحديث مكافآت الإيداع</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📊 <b>النسبة:</b>',
                '├── القديمة: <code>' . $old . '%</code>',
                '└── الجديدة: <b>' . $new . '%</b>',
                '',
                '👮 <b>بواسطة:</b> ' . ($admin?->username ?? 'الإدارة'),
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            app(NotificationService::class)->notifyGeneralChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about deposit bonus percent', [
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
                    text: '↩️ رجوع لمكافآت الإيداع',
                    callback_data: 'admin.deposit_bonus',
                ),
            );
    }
}
