<?php

namespace App\Telegram\Handlers\Admin\System;

use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SignupBonusService;
use App\Telegram\Conversations\Admin\System\EditSignupBonusConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class SignupBonusHandler
{
    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit($bot, $this->screenText(), $this->keyboard());
    }

    public function toggle(Nutgram $bot): void
    {
        $current  = (bool) Setting::get(SignupBonusService::KEY_ENABLED, false);
        $newValue = ! $current;

        Setting::set(SignupBonusService::KEY_ENABLED, $newValue ? '1' : '0');

        $this->safeAnswer(
            $bot,
            $newValue ? '🟢 جاري التفعيل...' : '🔴 جاري التعطيل...',
        );

        Log::info('Signup bonus toggled', [
            'admin_id' => $bot->userId(),
            'enabled'  => $newValue,
        ]);

        $this->safeEdit($bot, $this->screenText(), $this->keyboard());

        $this->notifyChannelToggle($bot, $newValue);
    }

    public function editNsp(Nutgram $bot): void
    {
        $this->startEdit($bot, 'nsp');
    }

    public function editUsd(Nutgram $bot): void
    {
        $this->startEdit($bot, 'usd');
    }

    private function startEdit(Nutgram $bot, string $currency): void
    {
        $this->safeAnswer($bot);

        Cache::put(
            "signup_bonus.edit.{$bot->userId()}",
            $currency,
            now()->addMinutes(10),
        );

        EditSignupBonusConversation::begin($bot);
    }

    // ═══════════════════════════════════════════════════════════
    //  Screen
    // ═══════════════════════════════════════════════════════════

    private function screenText(): string
    {
        $enabled  = (bool) Setting::get(SignupBonusService::KEY_ENABLED, false);
        $amountNsp = (float) Setting::get(SignupBonusService::KEY_AMOUNT_NSP, 0);
        $amountUsd = (float) Setting::get(SignupBonusService::KEY_AMOUNT_USD, 0);

        $statusIcon = $enabled ? '🟢' : '🔴';
        $statusText = $enabled ? 'مُفعّلة' : 'معطّلة';

        $lines = [
            '🎁 <b>مكافأة التسجيل</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            $statusIcon . ' <b>الحالة:</b> ' . $statusText,
            '',
            '💰 <b>مبالغ المكافأة:</b>',
        ];

        if ($amountNsp > 0) {
            $lines[] = '├── 💰 NSP: <b>' . number_format($amountNsp, 2) . '</b>';
        } else {
            $lines[] = '├── 💰 NSP: <i>غير مُفعّل</i>';
        }

        if ($amountUsd > 0) {
            $lines[] = '└── 💵 USD: <b>' . number_format($amountUsd, 2) . '</b>';
        } else {
            $lines[] = '└── 💵 USD: <i>غير مُفعّل</i>';
        }

        $lines[] = '';
        $lines[] = '💡 <i>عند تفعيل المكافأة، يحصل كل</i>';
        $lines[] = '<i>مستخدم جديد على الرصيد تلقائيًا.</i>';
        $lines[] = '';
        $lines[] = '📌 <b>ملاحظة:</b> تُمنح مرة واحدة فقط لكل مستخدم';

        return implode("\n", $lines);
    }

    private function keyboard(): InlineKeyboardMarkup
    {
        $enabled = (bool) Setting::get(SignupBonusService::KEY_ENABLED, false);

        return InlineKeyboardMarkup::make()
            // ─── 🎁 التفعيل/التعطيل ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: $enabled ? '🔴 تعطيل' : '🟢 تفعيل',
                    callback_data: 'signup_bonus.toggle',
                    style: $enabled ? ButtonStyle::DANGER : ButtonStyle::SUCCESS,
                ),
            )
            // ─── 💰 تعديل NSP + USD ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💰 تعديل NSP',
                    callback_data: 'signup_bonus.edit_nsp',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '💵 تعديل USD',
                    callback_data: 'signup_bonus.edit_usd',
                    style: ButtonStyle::PRIMARY,
                ),
            )
            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع للإعدادات',
                    callback_data: 'admin.system',
                ),
            );
    }

    // ═══════════════════════════════════════════════════════════
    //  إشعار القناة
    // ═══════════════════════════════════════════════════════════

    private function notifyChannelToggle(Nutgram $bot, bool $enabled): void
    {
        try {
            $amountNsp = (float) Setting::get(SignupBonusService::KEY_AMOUNT_NSP, 0);
            $amountUsd = (float) Setting::get(SignupBonusService::KEY_AMOUNT_USD, 0);
            $admin = User::where('telegram_id', $bot->userId())->first();

            if ($enabled) {
                $lines = [
                    '🎁 <b>تفعيل مكافأة التسجيل</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '✅ <b>الحالة:</b> مُفعّلة',
                    '',
                    '💰 <b>المكافآت:</b>',
                ];

                if ($amountNsp > 0) {
                    $lines[] = '├── 💰 NSP: <b>' . number_format($amountNsp, 2) . '</b>';
                }
                if ($amountUsd > 0) {
                    $lines[] = '└── 💵 USD: <b>' . number_format($amountUsd, 2) . '</b>';
                }

                $lines[] = '';
                $lines[] = '💡 كل مستخدم جديد يحصل على هذه المكافأة';
                $lines[] = '';
                $lines[] = '👮 <b>بواسطة:</b> ' . ($admin?->username ?? 'الإدارة');
                $lines[] = '📅 ' . now()->format('Y-m-d H:i');

                $text = implode("\n", $lines);
            } else {
                $text = implode("\n", [
                    '🎁 <b>تعطيل مكافأة التسجيل</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🔴 <b>الحالة:</b> معطّلة',
                    '',
                    '⏸ لن تُمنح مكافآت للمستخدمين الجدد.',
                    '',
                    '👮 <b>بواسطة:</b> ' . ($admin?->username ?? 'الإدارة'),
                    '📅 ' . now()->format('Y-m-d H:i'),
                ]);
            }

            app(NotificationService::class)->notifyGeneralChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about signup bonus toggle', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════

    private function safeAnswer(Nutgram $bot, ?string $text = null): void
    {
        try {
            $bot->answerCallbackQuery(text: $text);
        } catch (\Throwable $e) {
        }
    }

    private function safeEdit(Nutgram $bot, string $text, $keyboard): void
    {
        try {
            $bot->editMessageText(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not modified')) {
                return;
            }

            try {
                $bot->sendMessage(
                    text: $text,
                    parse_mode: 'HTML',
                    reply_markup: $keyboard,
                );
            } catch (\Throwable $e2) {
            }
        }
    }
}
