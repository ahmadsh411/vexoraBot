<?php

namespace App\Telegram\Handlers\Admin\System;

use App\Models\Setting;
use App\Models\User;
use App\Services\DepositBonusService;
use App\Services\NotificationService;
use App\Telegram\Conversations\Admin\System\EditDepositBonusConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class DepositBonusHandler
{
    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit($bot, $this->screenText(), $this->keyboard());
    }

    public function toggle(Nutgram $bot): void
    {
        $current  = (bool) Setting::get(DepositBonusService::KEY_ENABLED, false);
        $newValue = ! $current;

        Setting::set(DepositBonusService::KEY_ENABLED, $newValue ? '1' : '0');

        $this->safeAnswer(
            $bot,
            $newValue ? '🟢 جاري التفعيل...' : '🔴 جاري التعطيل...',
        );

        Log::info('Deposit bonus toggled', [
            'admin_id' => $bot->userId(),
            'enabled'  => $newValue,
        ]);

        $this->safeEdit($bot, $this->screenText(), $this->keyboard());

        $this->notifyChannelToggle($bot, $newValue);
    }

    public function editPercent(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        Cache::put(
            "deposit_bonus.edit.{$bot->userId()}",
            'percent',
            now()->addMinutes(10),
        );

        EditDepositBonusConversation::begin($bot);
    }

    // ═══════════════════════════════════════════════════════════
    //  Screen
    // ═══════════════════════════════════════════════════════════

    private function screenText(): string
    {
        $enabled = (bool) Setting::get(DepositBonusService::KEY_ENABLED, false);
        $percent = (int) Setting::get(DepositBonusService::KEY_PERCENT, 10);

        $statusIcon = $enabled ? '🟢' : '🔴';
        $statusText = $enabled ? 'مُفعّلة' : 'معطّلة';

        return implode("\n", [
            '🎁 <b>مكافآت الإيداع</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            $statusIcon . ' <b>الحالة:</b> ' . $statusText,
            '📊 <b>النسبة:</b> <b>' . $percent . '%</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💡 <i>عند تفعيل المكافآت، يحصل المستخدم</i>',
            '<i>على نسبة إضافية من كل إيداع ناجح.</i>',
            '',
            '📌 مثال: إيداع 1000 ل.س بنسبة ' . $percent . '%',
            '└── يحصل على <b>' . number_format(1000 * $percent / 100, 2) . ' ل.س</b> إضافية',
            '',
            '💡 <i>اضغط لتعديل الإعدادات</i>',
        ]);
    }

    private function keyboard(): InlineKeyboardMarkup
    {
        $enabled = (bool) Setting::get(DepositBonusService::KEY_ENABLED, false);

        return InlineKeyboardMarkup::make()
            // ─── 🎁 التفعيل/التعطيل ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: $enabled ? '🔴 تعطيل المكافآت' : '🟢 تفعيل المكافآت',
                    callback_data: 'deposit_bonus.toggle',
                    style: $enabled ? ButtonStyle::DANGER : ButtonStyle::SUCCESS,
                ),
            )
            // ─── 📊 تعديل النسبة ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📊 تعديل النسبة',
                    callback_data: 'deposit_bonus.edit',
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
            $percent = (int) Setting::get(DepositBonusService::KEY_PERCENT, 10);
            $admin = User::where('telegram_id', $bot->userId())->first();

            $text = $enabled
                ? implode("\n", [
                    '🎁 <b>تفعيل مكافآت الإيداع</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '✅ <b>الحالة:</b> مُفعّلة',
                    '📊 <b>النسبة:</b> ' . $percent . '%',
                    '',
                    '💰 يحصل المستخدمون الآن على نسبة إضافية',
                    'من كل إيداع ناجح.',
                    '',
                    '👮 <b>بواسطة:</b> ' . ($admin?->username ?? 'الإدارة'),
                    '📅 ' . now()->format('Y-m-d H:i'),
                ])
                : implode("\n", [
                    '🎁 <b>تعطيل مكافآت الإيداع</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🔴 <b>الحالة:</b> معطّلة',
                    '',
                    '⏸ لن تُضاف مكافآت على الإيداعات الجديدة.',
                    '',
                    '👮 <b>بواسطة:</b> ' . ($admin?->username ?? 'الإدارة'),
                    '📅 ' . now()->format('Y-m-d H:i'),
                ]);

            app(NotificationService::class)->notifyGeneralChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about deposit bonus toggle', [
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
                disable_web_page_preview: true,
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
                    disable_web_page_preview: true,
                );
            } catch (\Throwable $e2) {
            }
        }
    }
}
