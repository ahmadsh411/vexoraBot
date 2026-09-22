<?php

namespace App\Telegram\Conversations\Admin\System;

use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\NotificationService;
use App\Telegram\Conversations\BaseConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class EditExchangeRateConversation extends BaseConversation
{
    protected ?string $from = null;
    protected ?string $to   = null;

    public function start(Nutgram $bot): void
    {
        $data = Cache::pull("exchange_rate.edit.{$bot->userId()}");

        if (! $data || ! isset($data['from'], $data['to'])) {
            $this->keep($bot, '❌ انتهت صلاحية الجلسة.', reply_markup: $this->backKeyboard());
            $this->endAndClean($bot);
            return;
        }

        $this->from = $data['from'];
        $this->to   = $data['to'];

        $current = ExchangeRate::between($this->from, $this->to)
            ->latest()
            ->first();

        $currentText = $current
            ? '<code>' . number_format((float) $current->rate, 6) . '</code>'
            : '<i>لا يوجد سعر حالي</i>';

        $this->askTracked(
            $bot,
            implode("\n", [
                '✏️ <b>تعديل سعر الصرف</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '💱 <b>الزوج:</b> ' . $this->from . ' → ' . $this->to,
                '📌 <b>السعر الحالي:</b> ' . $currentText,
                '',
                '📤 <b>أرسل السعر الجديد:</b>',
                '',
                '💡 مثال: <code>15000</code>',
                '   (1 ' . $this->from . ' = 15000 ' . $this->to . ')',
                '',
                '↩️ أو /cancel للإلغاء',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('receiveRate');
    }

    public function receiveRate(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel' || $text === '') {
            $this->cancel($bot);
            return;
        }

        if (! is_numeric($text) || (float) $text <= 0) {
            $this->askTracked($bot, '⚠️ أرسل رقماً أكبر من 0:');
            return;
        }

        $rate = (float) $text;

        // اسأل عن العمولة
        $this->askTracked(
            $bot,
            implode("\n", [
                '💼 <b>العمولة</b>',
                '',
                '📤 أرسل نسبة العمولة (0-100)',
                '',
                '💡 <code>0</code> = بدون عمولة',
                '',
                '↩️ أو /skip للاستخدام 0',
            ]),
            parse_mode: 'HTML',
        );

        $this->setState($bot, ['rate' => $rate]);
        $this->next('receiveCommission');
    }

    public function receiveCommission(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        $commission = 0.0;

        if ($text !== '/skip' && is_numeric($text)) {
            $commission = (float) $text;

            if ($commission < 0 || $commission > 100) {
                $this->askTracked($bot, '⚠️ النسبة بين 0 و 100:');
                return;
            }
        }

        $state = $this->getState($bot);
        $rate  = $state['rate'] ?? 0;

        if ($rate <= 0) {
            $this->cancel($bot);
            return;
        }

        $admin = User::where('telegram_id', $bot->userId())->first();

        try {
            $service = app(ExchangeRateService::class);

            $newRate = $service->setRate(
                from: $this->from,
                to: $this->to,
                rate: $rate,
                commission: $commission,
                updatedBy: $admin,
                notes: 'تعديل من لوحة الأدمن',
            );

            // 🎉 إشعار القناة
            $this->notifyChannel($bot, $newRate, $admin);

            $this->keep(
                $bot,
                implode("\n", [
                    '✅ <b>تم حفظ السعر</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '💱 <b>الزوج:</b> ' . $this->from . ' → ' . $this->to,
                    '💰 <b>السعر:</b> <b>' . number_format((float) $newRate->rate, 4) . '</b>',
                    '💼 <b>العمولة:</b> ' . number_format((float) $newRate->commission_percent, 2) . '%',
                    '',
                    '💡 سيُطبَّق فورًا على كل التحويلات الجديدة.',
                ]),
                parse_mode: 'HTML',
                reply_markup: $this->backKeyboard(),
            );

            Log::info('Exchange rate updated', [
                'admin_id' => $bot->userId(),
                'from'     => $this->from,
                'to'       => $this->to,
                'rate'     => $rate,
                'commission' => $commission,
            ]);
        } catch (\Throwable $e) {
            Log::error('Exchange rate update failed', [
                'error' => $e->getMessage(),
            ]);

            $this->keep(
                $bot,
                '❌ فشل الحفظ: ' . $e->getMessage(),
                reply_markup: $this->backKeyboard(),
            );
        }

        $this->endAndClean($bot);
    }

    // ═══════════════════════════════════════════════════════════
    //  إشعار القناة
    // ═══════════════════════════════════════════════════════════

    private function notifyChannel(Nutgram $bot, ExchangeRate $rate, ?User $admin): void
    {
        try {
            $text = implode("\n", [
                '💱 <b>تحديث سعر الصرف</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '💱 <b>الزوج:</b> ' . $rate->from_currency . ' → ' . $rate->to_currency,
                '💰 <b>السعر الجديد:</b> <b>' . number_format((float) $rate->rate, 4) . '</b>',
                '💼 <b>العمولة:</b> ' . number_format((float) $rate->commission_percent, 2) . '%',
                '',
                '👮 <b>بواسطة:</b> ' . ($admin?->username ?? 'الإدارة'),
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            app(NotificationService::class)->notifyGeneralChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about exchange rate', [
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
                    text: '↩️ رجوع لأسعار الصرف',
                    callback_data: 'admin.exchange_rates',
                ),
            );
    }

    // State helpers
    private function stateKey(Nutgram $bot): string
    {
        return "exchange_rate_edit_state.{$bot->userId()}";
    }

    private function getState(Nutgram $bot): array
    {
        return Cache::get($this->stateKey($bot), []);
    }

    private function setState(Nutgram $bot, array $state): void
    {
        Cache::put($this->stateKey($bot), $state, now()->addMinutes(10));
    }
}
