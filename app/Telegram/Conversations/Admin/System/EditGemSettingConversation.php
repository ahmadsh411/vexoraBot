<?php

namespace App\Telegram\Conversations\Admin\System;

use App\Models\Setting;
use App\Models\User;
use App\Services\GemService;
use App\Services\NotificationService;
use App\Telegram\Conversations\BaseConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class EditGemSettingConversation extends BaseConversation
{
    protected ?string $key = null;

    private const LABELS = [
        'min_deposit_nsp'    => ['label' => '💰 حد الاكتساب (NSP)', 'field' => 'gems.min_deposit_nsp', 'type' => 'int'],
        'min_deposit_usd'    => ['label' => '💵 حد الاكتساب (USD)', 'field' => 'gems.min_deposit_usd', 'type' => 'decimal'],
        'exchange_min_gems'  => ['label' => '💱 الحد الأدنى للاستبدال (جواهر)', 'field' => 'gems.exchange_min_gems', 'type' => 'int'],
        'exchange_value_nsp' => ['label' => '💎 قيمة الاستبدال (NSP)', 'field' => 'gems.exchange_value_nsp', 'type' => 'int'],
        'wheel_min_gems'     => ['label' => '🎡 حد فتح العجلة (جواهر)', 'field' => 'gems.wheel_min_gems', 'type' => 'int'],
        'wheel_spins'        => ['label' => '🎰 عدد اللفات المكتسبة', 'field' => 'gems.wheel_spins', 'type' => 'int'],
    ];

    public function start(Nutgram $bot): void
    {
        $this->key = Cache::pull("gems.edit.{$bot->userId()}");

        if (! $this->key || ! isset(self::LABELS[$this->key])) {
            $this->keep($bot, '❌ انتهت صلاحية الجلسة.', reply_markup: $this->backKeyboard());
            $this->endAndClean($bot);
            return;
        }

        $config  = self::LABELS[$this->key];
        $current = Setting::get($config['field'], 0);

        $typeHint = $config['type'] === 'decimal'
            ? '💡 مثال: <code>1.5</code>'
            : '💡 مثال: <code>1000</code>';

        $this->askTracked(
            $bot,
            implode("\n", [
                '✏️ <b>تعديل: ' . $config['label'] . '</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📌 <b>القيمة الحالية:</b> <b>' . number_format((float) $current, 2) . '</b>',
                '',
                '📤 أرسل القيمة الجديدة:',
                '',
                $typeHint,
                '',
                '↩️ أو /cancel للإلغاء',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('receiveValue');
    }

    public function receiveValue(Nutgram $bot): void
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

        $value = (float) $text;

        if ($value < 0) {
            $this->askTracked($bot, '⚠️ القيمة يجب أن تكون 0 أو أكثر:');
            return;
        }

        $config = self::LABELS[$this->key];
        $old    = (float) Setting::get($config['field'], 0);

        Setting::set($config['field'], (string) $value);
        cache()->forget('setting.' . $config['field']);

        Log::info('Gem setting updated', [
            'admin_id' => $bot->userId(),
            'key'      => $this->key,
            'old'      => $old,
            'new'      => $value,
        ]);

        // إشعار القناة
        $this->notifyChannel($bot, $config['label'], $old, $value);

        $this->keep(
            $bot,
            implode("\n", [
                '✅ <b>تم التحديث</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📝 <b>' . $config['label'] . '</b>',
                '├── القديم: <code>' . number_format($old, 2) . '</code>',
                '└── الجديد: <b>' . number_format($value, 2) . '</b>',
            ]),
            parse_mode: 'HTML',
            reply_markup: $this->backKeyboard(),
        );

        $this->endAndClean($bot);
    }

    // ═══════════════════════════════════════════════════════════
    //  إشعار القناة
    // ═══════════════════════════════════════════════════════════

    private function notifyChannel(Nutgram $bot, string $label, float $old, float $new): void
    {
        try {
            $admin = User::where('telegram_id', $bot->userId())->first();

            $text = implode("\n", [
                '💎 <b>تحديث إعداد الجواهر</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📝 <b>' . $label . '</b>',
                '',
                '📊 <b>التغيير:</b>',
                '├── القديم: <code>' . number_format($old, 2) . '</code>',
                '└── الجديد: <b>' . number_format($new, 2) . '</b>',
                '',
                '👮 <b>بواسطة:</b> ' . ($admin?->username ?? 'الإدارة'),
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            app(NotificationService::class)->notifyGeneralChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about gem setting', [
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
                    text: '↩️ رجوع لنظام الجواهر',
                    callback_data: 'admin.gems',
                ),
            );
    }
}
