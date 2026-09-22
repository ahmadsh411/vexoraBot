<?php

namespace App\Telegram\Handlers\Admin\System;

use App\Models\GemBalance;
use App\Models\GemTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\GemService;
use App\Services\NotificationService;
use App\Telegram\Conversations\Admin\System\EditGemSettingConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class GemHandler
{
    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit($bot, $this->screenText(), $this->keyboard());
    }

    public function toggle(Nutgram $bot): void
    {
        $current  = (bool) Setting::get(GemService::KEY_ENABLED, false);
        $newValue = ! $current;

        Setting::set(GemService::KEY_ENABLED, $newValue ? '1' : '0');
        cache()->forget('setting.' . GemService::KEY_ENABLED);

        $this->safeAnswer(
            $bot,
            $newValue ? '🟢 جاري التفعيل...' : '🔴 جاري التعطيل...',
        );

        Log::info('Gems toggled', [
            'admin_id' => $bot->userId(),
            'enabled'  => $newValue,
        ]);

        $this->safeEdit($bot, $this->screenText(), $this->keyboard());
        $this->notifyChannelToggle($bot, $newValue);
    }

    public function edit(Nutgram $bot, string $key): void
    {
        $allowed = [
            'min_deposit_nsp',
            'min_deposit_usd',
            'exchange_min_gems',
            'exchange_value_nsp',
            'wheel_min_gems',
            'wheel_spins',
        ];

        if (! in_array($key, $allowed, true)) {
            $this->safeAlert($bot, '❌ إعداد غير معروف');
            return;
        }

        $this->safeAnswer($bot);

        Cache::put(
            "gems.edit.{$bot->userId()}",
            $key,
            now()->addMinutes(10),
        );

        EditGemSettingConversation::begin($bot);
    }

    // ═══════════════════════════════════════════════════════════
    //  Screen
    // ═══════════════════════════════════════════════════════════

    private function screenText(): string
    {
        $enabled = (bool) Setting::get(GemService::KEY_ENABLED, false);
        $service = app(GemService::class);

        $statusIcon = $enabled ? '🟢' : '🔴';
        $statusText = $enabled ? 'مُفعّل' : 'معطّل';

        $totalGems   = GemBalance::sum('balance');
        $activeUsers = GemBalance::where('balance', '>', 0)->count();

        $minNsp   = $service->getMinDepositNsp();
        $minUsd   = $service->getMinDepositUsd();
        $minEx    = $service->getExchangeMinGems();
        $valEx    = $service->getExchangeValueNsp();
        $minWh    = $service->getWheelMinGems();
        $spins    = $service->getWheelSpins();

        $lines = [
            '💎 <b>إدارة نظام الجواهر</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            $statusIcon . ' <b>الحالة:</b> ' . $statusText,
            '',
            '📊 <b>الإحصائيات:</b>',
            '├── 💎 إجمالي الجواهر: <b>' . number_format($totalGems) . '</b>',
            '└── 👥 المستخدمون: <b>' . $activeUsers . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '📥 <b>كيف يحصل المستخدم على جوهرة؟</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎁 <b>كل إيداع بقيمة:</b>',
            '├── 💰 ≥ <b>' . number_format($minNsp, 0) . ' NSP</b> → 1 جوهرة',
            '└── 💵 ≥ <b>' . number_format($minUsd, 2) . ' USD</b> → 1 جوهرة',
            '',
            '💡 <i>كل إيداع = جوهرة واحدة (فقط)</i>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '💱 <b>خيارات الاستبدال</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '1️⃣ <b>استبدال برصيد:</b>',
            '   ├── الحد الأدنى: <b>' . $minEx . '</b> جواهر',
            '   └── كل <b>' . $minEx . '</b> جواهر = <b>' . number_format($valEx, 0) . ' NSP</b>',
            '',
            '2️⃣ <b>فتح العجلة:</b>',
            '   ├── الحد الأدنى: <b>' . $minWh . '</b> جواهر',
            '   └── كل <b>' . $minWh . '</b> جواهر = <b>' . $spins . ' لفة</b>',
        ];

        return implode("\n", $lines);
    }

    private function keyboard(): InlineKeyboardMarkup
    {
        $enabled = (bool) Setting::get(GemService::KEY_ENABLED, false);

        return InlineKeyboardMarkup::make()
            // ─── 💎 التفعيل/التعطيل ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: $enabled ? '🔴 تعطيل النظام' : '🟢 تفعيل النظام',
                    callback_data: 'gems.toggle',
                    style: $enabled ? ButtonStyle::DANGER : ButtonStyle::SUCCESS,
                ),
            )
            // ─── 💰 حد الاكتساب (NSP/USD) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💰 حد الاكتساب (NSP)',
                    callback_data: 'gems.edit.min_deposit_nsp',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '💵 حد الاكتساب (USD)',
                    callback_data: 'gems.edit.min_deposit_usd',
                    style: ButtonStyle::PRIMARY,
                ),
            )
            // ─── 💱 حد الاستبدال + قيمة الاستبدال ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💱 حد الاستبدال',
                    callback_data: 'gems.edit.exchange_min_gems',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '💎 قيمة الاستبدال',
                    callback_data: 'gems.edit.exchange_value_nsp',
                    style: ButtonStyle::PRIMARY,
                ),
            )
            // ─── 🎡 حد العجلة + عدد اللفات ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🎡 حد العجلة',
                    callback_data: 'gems.edit.wheel_min_gems',
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '🎰 عدد اللفات',
                    callback_data: 'gems.edit.wheel_spins',
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
            $admin = User::where('telegram_id', $bot->userId())->first();
            $service = app(GemService::class);

            if ($enabled) {
                $text = implode("\n", [
                    '💎 <b>تفعيل نظام الجواهر</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '✅ <b>الحالة:</b> مُفعّل',
                    '',
                    '📥 <b>الاكتساب:</b>',
                    '├── 💰 NSP: ' . number_format($service->getMinDepositNsp(), 0) . '+',
                    '└── 💵 USD: ' . number_format($service->getMinDepositUsd(), 2) . '+',
                    '',
                    '💱 <b>الاستبدال:</b>',
                    '└── ' . $service->getExchangeMinGems() . ' جواهر = ' . number_format($service->getExchangeValueNsp(), 0) . ' NSP',
                    '',
                    '🎡 <b>العجلة:</b>',
                    '└── ' . $service->getWheelMinGems() . ' جواهر = ' . $service->getWheelSpins() . ' لفة',
                    '',
                    '👮 <b>بواسطة:</b> ' . ($admin?->username ?? 'الإدارة'),
                    '📅 ' . now()->format('Y-m-d H:i'),
                ]);
            } else {
                $text = implode("\n", [
                    '💎 <b>تعطيل نظام الجواهر</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🔴 <b>الحالة:</b> معطّل',
                    '',
                    '⏸ لن يحصل المستخدمون على جواهر جديدة.',
                    '',
                    '👮 <b>بواسطة:</b> ' . ($admin?->username ?? 'الإدارة'),
                    '📅 ' . now()->format('Y-m-d H:i'),
                ]);
            }

            app(NotificationService::class)->notifyGeneralChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about gems toggle', [
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

    private function safeAlert(Nutgram $bot, string $text): void
    {
        try {
            $bot->answerCallbackQuery(text: $text, show_alert: true);
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
