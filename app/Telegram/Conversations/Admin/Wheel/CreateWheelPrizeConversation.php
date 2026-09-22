<?php

namespace App\Telegram\Conversations\Admin\Wheel;

use App\Models\Wheel;
use App\Models\WheelPrize;
use App\Telegram\Conversations\BaseConversation;
use Illuminate\Support\Facades\Cache;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class CreateWheelPrizeConversation extends BaseConversation
{
    protected ?string $name = null;
    protected string $icon = '🎁';
    protected ?string $type = null;
    protected float $value = 0;
    protected string $currency = 'SYP';
    protected int $weight = 0;

    public function start(Nutgram $bot): void
    {
        $this->resetState($bot);

        $this->askTracked(
            $bot,
            implode("\n", [
                '➕ <b>إضافة جائزة جديدة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📝 <b>الخطوة 1/5:</b> اسم الجائزة',
                '',
                'أرسل الاسم (مثال: <code>50 ل.س</code>)',
                '',
                '↩️ أو /cancel للإلغاء',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('askName');
    }

    public function askName(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if (mb_strlen($text) < 2 || mb_strlen($text) > 100) {
            $this->askTracked($bot, '⚠️ الاسم بين 2 و 100 حرف. حاول مرة أخرى:');
            return;
        }

        $this->name = $text;

        $this->askTracked(
            $bot,
            implode("\n", [
                '🎨 <b>الخطوة 2/5:</b> الأيقونة',
                '',
                '📝 أرسل إيموجي (مثال: <code>💵</code>)',
                '',
                '↩️ أو /skip للاستخدام الافتراضي (🎁)',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('askIcon');
    }

    public function askIcon(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if ($text !== '' && $text !== '/skip' && ! str_starts_with($text, '/')) {
            $this->icon = $text;
        }

        $this->askTracked(
            $bot,
            implode("\n", [
                '📊 <b>الخطوة 3/5:</b> نوع الجائزة',
                '',
                'اختر من الأزرار:',
            ]),
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('💰 رصيد', callback_data: 'prize.type.balance', style: ButtonStyle::SUCCESS))
                ->addRow(InlineKeyboardButton::make('😢 فارغة', callback_data: 'prize.type.empty', style: ButtonStyle::PRIMARY))
                ->addRow(InlineKeyboardButton::make('♻️ لفة إضافية', callback_data: 'prize.type.recycle', style: ButtonStyle::PRIMARY))
                ->addRow(InlineKeyboardButton::make('❌ إلغاء', callback_data: 'prize.cancel')),
        );

        $this->next('askType');
    }

    public function askType(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;

        if ($data === 'prize.cancel') {
            $bot->answerCallbackQuery();
            $this->cancel($bot);
            return;
        }

        if (! in_array($data, ['prize.type.balance', 'prize.type.empty', 'prize.type.recycle'], true)) {
            $bot->answerCallbackQuery(text: '❌ اختر من الأزرار');
            return;
        }

        $bot->answerCallbackQuery();

        $this->type = str_replace('prize.type.', '', $data);

        if ($this->type === 'balance') {
            $this->askTracked(
                $bot,
                implode("\n", [
                    '💵 <b>الخطوة 4/5:</b> القيمة',
                    '',
                    '📝 أرسل قيمة الجائزة (رقم فقط)',
                    '',
                    'مثال: <code>50</code> أو <code>1000</code>',
                ]),
                parse_mode: 'HTML',
            );
            $this->next('askValue');
        } else {
            $this->value = 0;
            $this->askWeight($bot);
        }
    }

    public function askValue(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if (! is_numeric($text) || (float) $text <= 0) {
            $this->askTracked($bot, '⚠️ أرسل رقماً أكبر من 0:');
            return;
        }

        $this->value = (float) $text;

        $this->askTracked(
            $bot,
            implode("\n", [
                '💱 <b>اختر العملة:</b>',
            ]),
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('🇸🇾 ل.س SYP', callback_data: 'prize.cur.SYP', style: ButtonStyle::SUCCESS),
                    InlineKeyboardButton::make('💰 NSP', callback_data: 'prize.cur.NSP', style: ButtonStyle::SUCCESS),
                )
                ->addRow(InlineKeyboardButton::make('💵 USD', callback_data: 'prize.cur.USD', style: ButtonStyle::SUCCESS))
                ->addRow(InlineKeyboardButton::make('❌ إلغاء', callback_data: 'prize.cancel')),
        );

        $this->next('askCurrency');
    }

    public function askCurrency(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;

        if ($data === 'prize.cancel') {
            $bot->answerCallbackQuery();
            $this->cancel($bot);
            return;
        }

        if (! str_starts_with((string) $data, 'prize.cur.')) {
            $bot->answerCallbackQuery(text: '❌ اختر من الأزرار');
            return;
        }

        $bot->answerCallbackQuery();

        $this->currency = strtoupper(str_replace('prize.cur.', '', $data));

        $this->askWeight($bot);
    }

    private function askWeight(Nutgram $bot): void
    {
        $this->askTracked(
            $bot,
            implode("\n", [
                '🎯 <b>الخطوة 5/5:</b> النسبة',
                '',
                '📝 أرسل النسبة (0-100)',
                '',
                '💡 مثال: <code>20</code> = 20%',
                '',
                '⚠️ مجموع نسب الجوائز يجب أن يكون 100%',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('askWeightValue');
    }

    public function askWeightValue(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if (! is_numeric($text)) {
            $this->askTracked($bot, '⚠️ أرسل رقماً صحيحاً:');
            return;
        }

        $weight = (int) $text;

        if ($weight < 0 || $weight > 100) {
            $this->askTracked($bot, '⚠️ النسبة بين 0 و 100:');
            return;
        }

        $this->weight = $weight;

        $this->showConfirmation($bot);
    }

    private function showConfirmation(Nutgram $bot): void
    {
        $typeLabel = $this->typeLabel($this->type);

        $valueLine = $this->type === 'balance'
            ? '💰 <b>القيمة:</b> ' . number_format($this->value, 2) . ' ' . $this->currency
            : '💰 <b>القيمة:</b> —';

        $this->askTracked(
            $bot,
            implode("\n", [
                '📋 <b>تأكيد الجائزة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🎨 <b>الأيقونة:</b> ' . $this->icon,
                '📝 <b>الاسم:</b> ' . htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8'),
                '📊 <b>النوع:</b> ' . $typeLabel,
                $valueLine,
                '🎯 <b>النسبة:</b> ' . $this->weight . '%',
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                'هل تريد الحفظ؟',
            ]),
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('✅ حفظ', callback_data: 'prize.save', style: ButtonStyle::SUCCESS),
                    InlineKeyboardButton::make('❌ إلغاء', callback_data: 'prize.cancel'),
                ),
        );

        $this->next('save');
    }

    public function save(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;

        if ($data === 'prize.cancel') {
            $bot->answerCallbackQuery();
            $this->cancel($bot);
            return;
        }

        if ($data !== 'prize.save') {
            return;
        }

        $bot->answerCallbackQuery();

        $wheel = Wheel::first();

        if (! $wheel) {
            $this->keep($bot, '❌ لا توجد عجلة.', reply_markup: $this->backKeyboard());
            $this->endAndClean($bot);
            return;
        }

        try {
            $prize = WheelPrize::create([
                'wheel_id'   => $wheel->id,
                'name'       => $this->name,
                'icon'       => $this->icon,
                'type'       => $this->type,
                'value'      => $this->value,
                'currency'   => $this->currency,
                'weight'     => $this->weight,
                'color'      => '#FFD700',
                'sort_order' => (WheelPrize::where('wheel_id', $wheel->id)->max('sort_order') ?? 0) + 1,
                'is_active'  => true,
            ]);

            $total = (int) WheelPrize::where('wheel_id', $wheel->id)->sum('weight');
            $warn  = $total === 100 ? '' : "\n\n⚠️ <b>تحذير:</b> مجموع النسب الآن {$total}% (يجب أن يكون 100%)";

            $this->keep(
                $bot,
                implode("\n", [
                    '✅ <b>تم إنشاء الجائزة</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🎨 ' . $prize->icon . ' <b>' . htmlspecialchars($prize->name, ENT_QUOTES, 'UTF-8') . '</b>',
                    '📊 النوع: ' . $this->typeLabel($prize->type),
                    '💰 القيمة: ' . number_format($prize->value, 2) . ' ' . $prize->currency,
                    '🎯 النسبة: <b>' . $prize->weight . '%</b>',
                    '',
                    '📈 مجموع النسب: <b>' . $total . '%</b>',
                    $warn,
                ]),
                parse_mode: 'HTML',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '👁️ عرض الجائزة',
                            callback_data: 'wheel.admin.prize.show.' . $prize->id,
                        ),
                    )
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '↩️ رجوع للجوائز',
                            callback_data: 'wheel.admin.prizes',
                        ),
                    ),
            );
        } catch (\Throwable $e) {
            $this->keep(
                $bot,
                '❌ فشل الإنشاء: ' . $e->getMessage(),
                reply_markup: $this->backKeyboard(),
            );
        }

        $this->resetState($bot);
        $this->endAndClean($bot);
    }

    private function cancel(Nutgram $bot): void
    {
        $this->keep(
            $bot,
            '❌ تم الإلغاء.',
            reply_markup: $this->backKeyboard(),
        );
        $this->resetState($bot);
        $this->endAndClean($bot);
    }

    private function resetState(Nutgram $bot): void
    {
        $this->name = null;
        $this->icon = '🎁';
        $this->type = null;
        $this->value = 0;
        $this->currency = 'SYP';
        $this->weight = 0;
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'balance' => '💰 رصيد',
            'empty'   => '😢 فارغة',
            'recycle' => '♻️ لفة إضافية',
            default   => '❓',
        };
    }

    private function backKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع للجوائز',
                    callback_data: 'wheel.admin.prizes',
                ),
            );
    }
}
