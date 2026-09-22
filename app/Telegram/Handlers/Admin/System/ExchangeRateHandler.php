<?php

namespace App\Telegram\Handlers\Admin\System;

use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ExchangeRateHandler
{
    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
        $this->safeEdit($bot, $this->screenText(), $this->mainKeyboard());
    }

    public function show(Nutgram $bot, string $from, string $to): void
    {
        $this->safeAnswer($bot);

        $from = strtoupper($from);
        $to   = strtoupper($to);

        $rate = ExchangeRate::between($from, $to)->latest()->first();

        $this->safeEdit(
            $bot,
            $this->detailsText($from, $to, $rate),
            $this->detailsKeyboard($from, $to, $rate),
        );
    }

    public function edit(Nutgram $bot, string $from, string $to): void
    {
        $this->safeAnswer($bot);

        Cache::put(
            "exchange_rate.edit.{$bot->userId()}",
            ['from' => strtoupper($from), 'to' => strtoupper($to)],
            now()->addMinutes(10),
        );

        \App\Telegram\Conversations\Admin\System\EditExchangeRateConversation::begin($bot);
    }

    public function toggle(Nutgram $bot, string $from, string $to): void
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);

        $rate = ExchangeRate::between($from, $to)->latest()->first();

        if (! $rate) {
            $this->safeAlert($bot, '❌ السعر غير موجود');
            return;
        }

        $newState = ! $rate->is_active;
        $rate->update(['is_active' => $newState]);

        app(ExchangeRateService::class)->clearCache($from, $to);

        $this->safeAlert(
            $bot,
            $newState ? '🟢 تم التفعيل' : '🔴 تم التعطيل',
        );

        Log::info('Exchange rate toggled', [
            'admin_id' => $bot->userId(),
            'pair'     => "{$from}→{$to}",
            'state'    => $newState,
        ]);

        $this->show($bot, $from, $to);
    }

    // ═══════════════════════════════════════════════════════════
    //  Screen
    // ═══════════════════════════════════════════════════════════

    private function screenText(): string
    {
        $usdToNsp = ExchangeRate::between('USD', 'NSP')->latest()->first();
        $nspToUsd = ExchangeRate::between('NSP', 'USD')->latest()->first();

        $lines = [
            '💱 <b>أسعار الصرف</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
        ];

        if ($usdToNsp) {
            $status = $usdToNsp->is_active ? '🟢' : '🔴';
            $lines[] = $status . ' <b>USD → NSP</b>';
            $lines[] = '   <b>1 USD</b> = <code>' . number_format((float) $usdToNsp->rate, 2) . ' NSP</code>';
            if ((float) $usdToNsp->commission_percent > 0) {
                $lines[] = '   💼 العمولة: ' . number_format((float) $usdToNsp->commission_percent, 2) . '%';
            }
            $lines[] = '   📅 ' . $this->humanDate($usdToNsp->updated_at);
        } else {
            $lines[] = '⚪ <b>USD → NSP</b>';
            $lines[] = '   <i>غير مُعرّف بعد</i>';
        }

        $lines[] = '';

        if ($nspToUsd) {
            $status = $nspToUsd->is_active ? '🟢' : '🔴';
            $lines[] = $status . ' <b>NSP → USD</b>';
            $lines[] = '   <b>1 NSP</b> = <code>' . number_format((float) $nspToUsd->rate, 6) . ' USD</code>';
            if ((float) $nspToUsd->commission_percent > 0) {
                $lines[] = '   💼 العمولة: ' . number_format((float) $nspToUsd->commission_percent, 2) . '%';
            }
            $lines[] = '   📅 ' . $this->humanDate($nspToUsd->updated_at);
        } else {
            $lines[] = '⚪ <b>NSP → USD</b>';
            $lines[] = '   <i>غير مُعرّف بعد</i>';
        }

        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━';
        $lines[] = '⚙️ <i>اضغط على أي زوج للتعديل</i>';

        return implode("\n", $lines);
    }

    private function detailsText(string $from, string $to, ?ExchangeRate $rate): string
    {
        $lines = [
            '💱 <b>' . $from . ' → ' . $to . '</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
        ];

        if (! $rate) {
            $lines[] = '⚪ <b>الحالة:</b> غير مُعرّف';
            $lines[] = '';
            $lines[] = '📤 اضغط "تعيين السعر" للبدء';
            return implode("\n", $lines);
        }

        $status = $rate->is_active ? '🟢 مُفعّل' : '🔴 مُعطّل';

        $lines[] = $status;
        $lines[] = '';
        $lines[] = '💰 <b>السعر:</b>';
        $lines[] = '   1 ' . $from . ' = <code>' . number_format((float) $rate->rate, 4) . '</code> ' . $to;
        $lines[] = '';
        $lines[] = '💼 <b>العمولة:</b> ' . number_format((float) $rate->commission_percent, 2) . '%';
        $lines[] = '';
        $lines[] = '📅 <b>آخر تحديث:</b> ' . $this->humanDate($rate->updated_at);

        if ($rate->updater) {
            $lines[] = '👮 <b>بواسطة:</b> ' . $rate->updater->username;
        }

        return implode("\n", $lines);
    }

    private function mainKeyboard(): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        $usdToNsp = ExchangeRate::between('USD', 'NSP')->latest()->first();
        $nspToUsd = ExchangeRate::between('NSP', 'USD')->latest()->first();

        $usdLabel = $usdToNsp
            ? ($usdToNsp->is_active ? '🟢' : '🔴') . ' USD → NSP'
            : '➕ USD → NSP';

        $nspLabel = $nspToUsd
            ? ($nspToUsd->is_active ? '🟢' : '🔴') . ' NSP → USD'
            : '➕ NSP → USD';

        // ─── 💱 أزواج العملات ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: $usdLabel,
                callback_data: 'exchange_rate.show.USD.NSP',
                style: ButtonStyle::PRIMARY,
            ),
            InlineKeyboardButton::make(
                text: $nspLabel,
                callback_data: 'exchange_rate.show.NSP.USD',
                style: ButtonStyle::PRIMARY,
            ),
        );

        // ─── ↩️ رجوع (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '⬅️ رجوع للإعدادات',
                callback_data: 'admin.system',
            ),
        );

        return $keyboard;
    }

    private function detailsKeyboard(string $from, string $to, ?ExchangeRate $rate): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        // ─── ✏️ تعديل السعر ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: $rate ? '✏️ تعديل السعر' : '➕ تعيين السعر',
                callback_data: 'exchange_rate.edit.' . $from . '.' . $to,
                style: ButtonStyle::PRIMARY,
            ),
        );

        // ─── 🔴/🟢 تعطيل/تفعيل ───
        if ($rate) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: $rate->is_active ? '🔴 تعطيل' : '🟢 تفعيل',
                    callback_data: 'exchange_rate.toggle.' . $from . '.' . $to,
                    style: $rate->is_active ? ButtonStyle::DANGER : ButtonStyle::SUCCESS,
                ),
            );
        }

        // ─── ↩️ رجوع (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '⬅️ رجوع',
                callback_data: 'admin.exchange_rates',
            ),
        );

        return $keyboard;
    }

    // ═══════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════

    private function humanDate(?\Carbon\Carbon $date): string
    {
        if (! $date) return '—';

        if ($date->isToday()) {
            return 'اليوم ' . $date->format('H:i');
        }

        if ($date->isYesterday()) {
            return 'أمس ' . $date->format('H:i');
        }

        return $date->format('Y-m-d H:i');
    }

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
