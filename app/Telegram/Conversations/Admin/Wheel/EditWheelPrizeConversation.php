<?php

namespace App\Telegram\Conversations\Admin\Wheel;

use App\Models\WheelPrize;
use App\Telegram\Conversations\BaseConversation;
use Illuminate\Support\Facades\Cache;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class EditWheelPrizeConversation extends BaseConversation
{
    protected ?int $prizeId = null;

    // القيم الجديدة
    protected ?string $name = null;
    protected ?string $icon = null;
    protected ?string $type = null;
    protected float $value = 0;
    protected string $currency = 'SYP';
    protected int $weight = 0;

    // القيم الأصلية (للعرض فقط)
    protected string $origName = '';
    protected string $origIcon = '';
    protected string $origType = '';
    protected float $origValue = 0;
    protected string $origCurrency = '';
    protected int $origWeight = 0;

    // ═══════════════════════════════════════════════════════════
    //  🚀 البدء
    // ═══════════════════════════════════════════════════════════
    public function start(Nutgram $bot): void
    {
        $this->prizeId = Cache::pull("wheel_prize.edit.id.{$bot->userId()}");

        if (! $this->prizeId) {
            $this->keep($bot, '❌ انتهت صلاحية الجلسة.', reply_markup: $this->backKeyboard(null));
            $this->endAndClean($bot);
            return;
        }

        $prize = WheelPrize::find($this->prizeId);

        if (! $prize) {
            $this->keep($bot, '❌ الجائزة غير موجودة.', reply_markup: $this->backKeyboard(null));
            $this->endAndClean($bot);
            return;
        }

        // تعبئة القيم الأصلية
        $this->origName     = $prize->name;
        $this->origIcon     = $prize->icon;
        $this->origType     = $prize->type;
        $this->origValue    = (float) $prize->value;
        $this->origCurrency = $prize->currency;
        $this->origWeight   = (int) $prize->weight;

        // تعبئة القيم الجديدة من الأصلية
        $this->name     = $prize->name;
        $this->icon     = $prize->icon;
        $this->type     = $prize->type;
        $this->value    = (float) $prize->value;
        $this->currency = $prize->currency;
        $this->weight   = (int) $prize->weight;

        $this->askTracked(
            $bot,
            implode("\n", [
                '✏️ <b>تعديل الجائزة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📝 <b>الخطوة 1/6:</b> الاسم',
                '',
                '📌 <b>الحالي:</b>',
                '<code>' . htmlspecialchars($this->origName, ENT_QUOTES, 'UTF-8') . '</code>',
                '',
                '📤 أرسل الاسم الجديد (أو /skip للإبقاء):',
                '',
                '↩️ أو /cancel للإلغاء',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('askName');
    }

    // ═══════════════════════════════════════════════════════════
    //  1) الاسم
    // ═══════════════════════════════════════════════════════════
    public function askName(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if ($text !== '' && $text !== '/skip' && ! str_starts_with($text, '/')) {
            if (mb_strlen($text) < 2 || mb_strlen($text) > 100) {
                $this->askTracked($bot, '⚠️ الاسم بين 2 و 100 حرف. حاول مرة أخرى:');
                return;
            }
            $this->name = $text;
        }

        $this->askTracked(
            $bot,
            implode("\n", [
                '🎨 <b>الخطوة 2/6:</b> الأيقونة',
                '',
                '📌 <b>الحالية:</b> ' . $this->origIcon,
                '',
                '📤 أرسل إيموجي جديد',
                '',
                '↩️ أو /skip للإبقاء',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('askIcon');
    }

    // ═══════════════════════════════════════════════════════════
    //  2) الأيقونة
    // ═══════════════════════════════════════════════════════════
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
                '📊 <b>الخطوة 3/6:</b> النوع',
                '',
                '📌 <b>الحالي:</b> ' . $this->typeLabel($this->origType),
                '',
                'اختر النوع الجديد:',
            ]),
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('💰 رصيد', callback_data: 'edit.type.balance', style: ButtonStyle::SUCCESS))
                ->addRow(InlineKeyboardButton::make('😢 فارغة', callback_data: 'edit.type.empty', style: ButtonStyle::PRIMARY))
                ->addRow(InlineKeyboardButton::make('♻️ لفة إضافية', callback_data: 'edit.type.recycle', style: ButtonStyle::PRIMARY))
                ->addRow(InlineKeyboardButton::make('⏭ تخطي', callback_data: 'edit.type.skip'))
                ->addRow(InlineKeyboardButton::make('❌ إلغاء', callback_data: 'edit.cancel')),
        );

        $this->next('askType');
    }

    // ═══════════════════════════════════════════════════════════
    //  3) النوع
    // ═══════════════════════════════════════════════════════════
    public function askType(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;

        if ($data === 'edit.cancel') {
            $bot->answerCallbackQuery();
            $this->cancel($bot);
            return;
        }

        if ($data === 'edit.type.skip') {
            $bot->answerCallbackQuery();
            $this->type = $this->origType;
            $this->proceedAfterType($bot);
            return;
        }

        if (! in_array($data, ['edit.type.balance', 'edit.type.empty', 'edit.type.recycle'], true)) {
            $bot->answerCallbackQuery(text: '❌ اختر من الأزرار');
            return;
        }

        $bot->answerCallbackQuery();

        $this->type = str_replace('edit.type.', '', $data);
        $this->proceedAfterType($bot);
    }

    private function proceedAfterType(Nutgram $bot): void
    {
        if ($this->type === 'balance') {
            $this->askTracked(
                $bot,
                implode("\n", [
                    '💰 <b>الخطوة 4/6:</b> القيمة',
                    '',
                    '📌 <b>الحالية:</b> ' . number_format($this->origValue, 2) . ' ' . $this->origCurrency,
                    '',
                    '📤 أرسل القيمة الجديدة (رقم فقط)',
                    '',
                    '↩️ أو /skip للإبقاء',
                ]),
                parse_mode: 'HTML',
            );
            $this->next('askValue');
        } else {
            $this->value = 0;
            $this->askWeight($bot);
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  4) القيمة
    // ═══════════════════════════════════════════════════════════
    public function askValue(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if ($text !== '' && $text !== '/skip' && ! str_starts_with($text, '/')) {
            if (! is_numeric($text) || (float) $text <= 0) {
                $this->askTracked($bot, '⚠️ أرسل رقماً أكبر من 0:');
                return;
            }
            $this->value = (float) $text;
        }

        $this->askTracked(
            $bot,
            implode("\n", [
                '💱 <b>الخطوة 5/6:</b> العملة',
                '',
                '📌 <b>الحالية:</b> ' . $this->origCurrency,
                '',
                'اختر العملة الجديدة:',
            ]),
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('🇸🇾 SYP', callback_data: 'edit.cur.SYP', style: ButtonStyle::SUCCESS),
                    InlineKeyboardButton::make('💰 NSP', callback_data: 'edit.cur.NSP', style: ButtonStyle::SUCCESS),
                )
                ->addRow(InlineKeyboardButton::make('💵 USD', callback_data: 'edit.cur.USD', style: ButtonStyle::SUCCESS))
                ->addRow(InlineKeyboardButton::make('⏭ تخطي', callback_data: 'edit.cur.skip'))
                ->addRow(InlineKeyboardButton::make('❌ إلغاء', callback_data: 'edit.cancel')),
        );

        $this->next('askCurrency');
    }

    // ═══════════════════════════════════════════════════════════
    //  5) العملة
    // ═══════════════════════════════════════════════════════════
    public function askCurrency(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;

        if ($data === 'edit.cancel') {
            $bot->answerCallbackQuery();
            $this->cancel($bot);
            return;
        }

        if ($data === 'edit.cur.skip') {
            $bot->answerCallbackQuery();
            $this->currency = $this->origCurrency;
            $this->askWeight($bot);
            return;
        }

        if (! str_starts_with((string) $data, 'edit.cur.')) {
            $bot->answerCallbackQuery(text: '❌ اختر من الأزرار');
            return;
        }

        $bot->answerCallbackQuery();
        $this->currency = strtoupper(str_replace('edit.cur.', '', $data));
        $this->askWeight($bot);
    }

    // ═══════════════════════════════════════════════════════════
    //  6) النسبة
    // ═══════════════════════════════════════════════════════════
    private function askWeight(Nutgram $bot): void
    {
        $this->askTracked(
            $bot,
            implode("\n", [
                '🎯 <b>الخطوة 6/6:</b> النسبة',
                '',
                '📌 <b>الحالية:</b> ' . $this->origWeight . '%',
                '',
                '📤 أرسل النسبة الجديدة (0-100)',
                '',
                '↩️ أو /skip للإبقاء',
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

        if ($text !== '' && $text !== '/skip' && ! str_starts_with($text, '/')) {
            if (! is_numeric($text)) {
                $this->askTracked($bot, '⚠️ أرسل رقماً:');
                return;
            }

            $weight = (int) $text;

            if ($weight < 0 || $weight > 100) {
                $this->askTracked($bot, '⚠️ النسبة بين 0 و 100:');
                return;
            }

            $this->weight = $weight;
        }

        $this->showConfirmation($bot);
    }

    // ═══════════════════════════════════════════════════════════
    //  📋 معاينة + حفظ
    // ═══════════════════════════════════════════════════════════
    private function showConfirmation(Nutgram $bot): void
    {
        $typeLabel = $this->typeLabel($this->type);

        $valueLine = $this->type === 'balance'
            ? '💰 <b>القيمة:</b> ' . number_format($this->value, 2) . ' ' . $this->currency
            : '💰 <b>القيمة:</b> —';

        $this->askTracked(
            $bot,
            implode("\n", [
                '📋 <b>تأكيد التعديل</b>',
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
                    InlineKeyboardButton::make('✅ حفظ', callback_data: 'edit.save', style: ButtonStyle::SUCCESS),
                    InlineKeyboardButton::make('❌ إلغاء', callback_data: 'edit.cancel'),
                ),
        );

        $this->next('save');
    }

    // ═══════════════════════════════════════════════════════════
    //  💾 الحفظ
    // ═══════════════════════════════════════════════════════════
    public function save(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;

        if ($data === 'edit.cancel') {
            $bot->answerCallbackQuery();
            $this->cancel($bot);
            return;
        }

        if ($data !== 'edit.save') {
            return;
        }

        $bot->answerCallbackQuery();

        $prize = WheelPrize::find($this->prizeId);

        if (! $prize) {
            $this->keep($bot, '❌ الجائزة غير موجودة.', reply_markup: $this->backKeyboard(null));
            $this->endAndClean($bot);
            return;
        }

        try {
            $prize->update([
                'name'     => $this->name,
                'icon'     => $this->icon,
                'type'     => $this->type,
                'value'    => $this->value,
                'currency' => $this->currency,
                'weight'   => $this->weight,
            ]);
            $prize->refresh();

            $total = (int) WheelPrize::where('wheel_id', $prize->wheel_id)->sum('weight');
            $warn  = $total === 100 ? '' : "\n\n⚠️ <b>تحذير:</b> مجموع النسب الآن {$total}% (يجب أن يكون 100%)";

            $this->keep(
                $bot,
                implode("\n", [
                    '✅ <b>تم تحديث الجائزة</b>',
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
                '❌ فشل التحديث: ' . $e->getMessage(),
                reply_markup: $this->backKeyboard($this->prizeId),
            );
        }

        $this->endAndClean($bot);
    }

    // ═══════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════
    private function typeLabel(string $type): string
    {
        return match ($type) {
            'balance' => '💰 رصيد',
            'empty'   => '😢 فارغة',
            'recycle' => '♻️ لفة إضافية',
            default   => '❓',
        };
    }

    private function cancel(Nutgram $bot): void
    {
        $this->keep(
            $bot,
            '❌ تم الإلغاء.',
            reply_markup: $this->backKeyboard($this->prizeId),
        );
        $this->endAndClean($bot);
    }

    private function backKeyboard(?int $prizeId): InlineKeyboardMarkup
    {
        $button = $prizeId
            ? InlineKeyboardButton::make(
                text: '↩️ رجوع للجائزة',
                callback_data: 'wheel.admin.prize.show.' . $prizeId,
            )
            : InlineKeyboardButton::make(
                text: '↩️ رجوع للجوائز',
                callback_data: 'wheel.admin.prizes',
            );

        return InlineKeyboardMarkup::make()->addRow($button);
    }
}
