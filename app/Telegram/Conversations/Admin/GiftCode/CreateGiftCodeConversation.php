<?php

namespace App\Telegram\Conversations\Admin\GiftCode;

use App\Models\User;
use App\Services\GiftCodeService;
use App\Telegram\Conversations\BaseConversation;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class CreateGiftCodeConversation extends BaseConversation
{
    public ?float  $value = null;
    public ?string $currency = null;
    public ?int    $seconds = null;
    public ?int    $maxUses = null;
    public ?string $note = null;

    public function start(Nutgram $bot): void
    {
        $this->askTracked(
            $bot,
            implode("\n", [
                '🎁 <b>إنشاء كود هدية جديد</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '💰 أرسل <b>قيمة الهدية</b> (رقم فقط):',
                '',
                'مثال: <code>5000</code>',
            ]),
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()->addRow(
                InlineKeyboardButton::make('❌ إلغاء', callback_data: 'gift.create.cancel'),
            ),
        );

        $this->next('askValue');
    }

    public function askValue(Nutgram $bot): void
    {
        if ($bot->isCallbackQuery()) {
            $bot->answerCallbackQuery();
        }

        $text = trim((string) ($bot->message()?->text ?? ''));

        if (! is_numeric($text) || (float) $text <= 0) {
            $this->askTracked($bot, '❌ أرسل رقماً أكبر من صفر.');
            return;
        }

        $this->value = (float) $text;

        $this->askTracked(
            $bot,
            implode("\n", [
                '💱 اختر <b>العملة</b>:',
                '',
                "💰 القيمة: <b>{$this->value}</b>",
            ]),
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('🇸🇾 NSP (ل.س)', callback_data: 'gift.currency.NSP', style: ButtonStyle::SUCCESS),
                    InlineKeyboardButton::make('🇺🇸 USD (دولار)', callback_data: 'gift.currency.USD', style: ButtonStyle::SUCCESS),
                )
                ->addRow(
                    InlineKeyboardButton::make('❌ إلغاء', callback_data: 'gift.create.cancel'),
                ),
        );

        $this->next('askCurrency');
    }

    public function askCurrency(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;

        if ($data === 'gift.create.cancel') {
            $this->cancel($bot);
            return;
        }

        if (! in_array($data, ['gift.currency.NSP', 'gift.currency.USD'], true)) {
            $bot->answerCallbackQuery(text: '❌ اختر عملة صحيحة.');
            return;
        }

        $bot->answerCallbackQuery();
        $this->currency = str_replace('gift.currency.', '', $data);

        $this->askTracked(
            $bot,
            implode("\n", [
                '⏳ <b>أرسل مدة الصلاحية</b> كتابةً:',
                '',
                "💰 القيمة: <b>{$this->value} {$this->currency}</b>",
                '',
                '━━━━━━━━━━━━━━━━━━',
                '📌 <b>الصيغ المدعومة:</b>',
                '',
                '• <code>30s</code> — 30 ثانية',
                '• <code>5m</code> — 5 دقائق',
                '• <code>2h</code> — ساعتان',
                '• <code>1d</code> — يوم كامل',
                '',
                '💡 أيضاً بالعربية:',
                '• <code>10 ثواني</code>',
                '• <code>5 دقائق</code>',
                '• <code>2 ساعات</code>',
                '• <code>1 يوم</code>',
            ]),
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()->addRow(
                InlineKeyboardButton::make('❌ إلغاء', callback_data: 'gift.create.cancel'),
            ),
        );

        $this->next('askDuration');
    }

    public function askDuration(Nutgram $bot): void
    {
        if ($bot->isCallbackQuery() && $bot->callbackQuery()?->data === 'gift.create.cancel') {
            $this->cancel($bot);
            return;
        }

        $text = trim((string) ($bot->message()?->text ?? ''));
        $seconds = GiftCodeService::parseDuration($text);

        if ($seconds === null) {
            $this->askTracked(
                $bot,
                implode("\n", [
                    '❌ <b>صيغة المدة غير صحيحة.</b>',
                    '',
                    '📌 استخدم إحدى الصيغ:',
                    '',
                    '• <code>30s</code> — 30 ثانية',
                    '• <code>5m</code> — 5 دقائق',
                    '• <code>2h</code> — ساعتان',
                    '• <code>1d</code> — يوم',
                    '',
                    '💡 أو بالعربية:',
                    '• <code>10 ثواني</code>',
                    '• <code>5 دقائق</code>',
                    '• <code>ساعتين</code>',
                    '• <code>يوم</code>',
                ]),
                parse_mode: 'HTML',
            );
            return;
        }

        $this->seconds = $seconds;
        $humanTime = $this->humanSeconds($seconds);

        $this->askTracked(
            $bot,
            implode("\n", [
                '👥 <b>عدد المستخدمين المسموح لهم</b>:',
                '',
                "⏳ الصلاحية: <b>{$humanTime}</b>",
                '',
                '• <code>1</code> = أول مستخدم فقط',
                '• <code>10</code> = أول 10 مستخدمين',
            ]),
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('1️⃣ مستخدم واحد', callback_data: 'gift.uses.1', style: ButtonStyle::PRIMARY),
                    InlineKeyboardButton::make('🔟 عشرة', callback_data: 'gift.uses.10', style: ButtonStyle::PRIMARY),
                )
                ->addRow(
                    InlineKeyboardButton::make('💯 مئة', callback_data: 'gift.uses.100', style: ButtonStyle::PRIMARY),
                    InlineKeyboardButton::make('♾️ يدوي', callback_data: 'gift.uses.manual', style: ButtonStyle::PRIMARY),
                )
                ->addRow(
                    InlineKeyboardButton::make('❌ إلغاء', callback_data: 'gift.create.cancel'),
                ),
        );

        $this->next('askMaxUses');
    }

    public function askMaxUses(Nutgram $bot): void
    {
        if ($bot->isCallbackQuery()) {
            $data = $bot->callbackQuery()?->data;

            if ($data === 'gift.create.cancel') {
                $this->cancel($bot);
                return;
            }

            if ($data === 'gift.uses.manual') {
                $bot->answerCallbackQuery();
                $this->askTracked(
                    $bot,
                    '✍️ أرسل <b>عدد المستخدمين</b> يدوياً (1 - 10000):',
                    parse_mode: 'HTML',
                );
                $this->next('askMaxUsesManual');
                return;
            }

            if (preg_match('/^gift\.uses\.(\d+)$/', (string) $data, $m)) {
                $bot->answerCallbackQuery();
                $this->maxUses = (int) $m[1];
                $this->askNote($bot);
                return;
            }

            $bot->answerCallbackQuery(text: '❌ خيار غير صحيح.');
            return;
        }

        $text = trim((string) ($bot->message()?->text ?? ''));

        if (! is_numeric($text) || (int) $text < 1 || (int) $text > 10000) {
            $this->askTracked($bot, '❌ أرسل رقماً بين 1 و 10000.');
            return;
        }

        $this->maxUses = (int) $text;
        $this->askNote($bot);
    }

    public function askMaxUsesManual(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()?->text ?? ''));

        if (! is_numeric($text) || (int) $text < 1 || (int) $text > 10000) {
            $this->askTracked($bot, '❌ أرسل رقماً بين 1 و 10000.');
            return;
        }

        $this->maxUses = (int) $text;
        $this->askNote($bot);
    }

    public function askNote(Nutgram $bot): void
    {
        $this->askTracked(
            $bot,
            implode("\n", [
                '📝 أرسل <b>ملاحظة داخلية</b> (اختياري):',
                '',
                'مثال: "كود ترويجي لإعلان رمضان"',
                '',
                'أو أرسل <code>/skip</code> للتخطي.',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('saveNote');
    }

    public function saveNote(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()?->text ?? ''));

        $this->note = ($text === '' || $text === '/skip') ? null : $text;

        $humanTime = $this->humanSeconds($this->seconds);

        $preview = implode("\n", [
            '📋 <b>تأكيد إنشاء الكود</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            "💰 القيمة: <b>{$this->value} {$this->currency}</b>",
            "⏳ الصلاحية: <b>{$humanTime}</b>",
            "👥 عدد المستخدمين: <b>{$this->maxUses}</b>",
            "📝 ملاحظة: <b>" . ($this->note ?? '—') . '</b>',
            '',
            'هل تريد المتابعة؟',
        ]);

        $this->askTracked(
            $bot,
            $preview,
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('✅ تأكيد ونشر', callback_data: 'gift.create.confirm', style: ButtonStyle::SUCCESS),
                    InlineKeyboardButton::make('❌ إلغاء', callback_data: 'gift.create.cancel'),
                ),
        );

        $this->next('confirm');
    }

    public function confirm(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;

        if ($data === 'gift.create.cancel') {
            $this->cancel($bot);
            return;
        }

        if ($data !== 'gift.create.confirm') {
            return;
        }

        $bot->answerCallbackQuery();

        $admin = User::where('telegram_id', $bot->userId())->first();

        if (! $admin || ! $admin->is_admin) {
            $this->keep(
                $bot,
                '🚫 هذا الأمر للمشرفين فقط.',
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        $channelId = config('services.telegram.main_channel_id');

        if (! $channelId) {
            $this->keep(
                $bot,
                '❌ القناة الرئيسية غير مضبوطة في الإعدادات.',
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        try {
            $service = app(GiftCodeService::class);

            $gift = $service->createCode(
                adminUserId: $admin->id,
                value: $this->value,
                currency: $this->currency,
                secondsValid: $this->seconds,
                maxUses: $this->maxUses,
                note: $this->note,
                channelId: (int) $channelId,
            );

            $this->publishToChannel($bot, $gift, $service);

            $humanTime = $this->humanSeconds($this->seconds);

            $this->keep(
                $bot,
                implode("\n", [
                    '✅ <b>تم إنشاء الكود ونشره بنجاح!</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    "🔐 الكود: <code>{$gift->code}</code>",
                    "💰 القيمة: <b>{$gift->value} {$gift->currency}</b>",
                    "⏳ الصلاحية: <b>{$humanTime}</b>",
                    "👥 عدد المستخدمين: <b>{$this->maxUses}</b>",
                ]),
                parse_mode: 'HTML',
                reply_markup: $this->backKeyboard(),
            );
        } catch (\Throwable $e) {
            Log::error('GiftCode creation failed', [
                'admin_id' => $admin->id,
                'error'    => $e->getMessage(),
            ]);

            $this->keep(
                $bot,
                "❌ فشل إنشاء الكود: {$e->getMessage()}",
                reply_markup: $this->backKeyboard(),
            );
        }

        $this->endAndClean($bot);
    }

    protected function publishToChannel(Nutgram $bot, $gift, GiftCodeService $service): void
    {
        $humanTime = $this->humanSeconds($this->seconds);

        $text = implode("\n", [
            '🎁 <b>كود هدية جديد (للنشر اليدوي)</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📋 <b>النص الجاهز للنشر:</b>',
            '',
            '🎁 <b>كود هدية جديد!</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🔐 الكود: <code>' . $gift->code . '</code>',
            '💰 القيمة: <b>' . number_format($gift->value, 2) . ' ' . $gift->currency . '</b>',
            '⏳ الصلاحية: <b>' . $humanTime . '</b>',
            $this->maxUses === 1
                ? '⚡ <i>أول مستخدم فقط!</i>'
                : '👥 متاح لـ <b>' . $this->maxUses . '</b> مستخدم',
            '',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>تفاصيل الكود (للأدمن):</b>',
            '',
            '🎫 الكود: <code>' . $gift->code . '</code>',
            '💰 القيمة: <b>' . number_format($gift->value, 2) . ' ' . $gift->currency . '</b>',
            '⏳ الصلاحية: <b>' . $this->seconds . ' ثانية</b> (' . $humanTime . ')',
            '👥 عدد المستخدمين: <b>' . $this->maxUses . '</b>',
            '📝 ملاحظة: <b>' . ($this->note ?? '—') . '</b>',
            '',
            '🕐 ينتهي في: ' . $gift->expires_at->format('Y-m-d H:i:s'),
            '',
            '<i>💡 انسخ النص أعلاه وانشره في قناة المستخدمين</i>',
        ]);

        $success = app(\App\Services\NotificationService::class)
            ->notifyGeneralChannel($bot, $text);

        if ($success) {
            Log::info('GiftCode: published to general channel', [
                'gift_id' => $gift->id,
                'code'    => $gift->code,
            ]);
        } else {
            Log::warning('GiftCode: failed to publish to general channel', [
                'gift_id' => $gift->id,
                'code'    => $gift->code,
            ]);
        }
    }

    protected function cancel(Nutgram $bot): void
    {
        if ($bot->isCallbackQuery()) {
            try {
                $bot->answerCallbackQuery(text: 'تم الإلغاء');
            } catch (\Throwable $e) {
            }
        }

        $this->keep(
            $bot,
            '❌ تم إلغاء العملية.',
            reply_markup: $this->backKeyboard(),
        );

        $this->endAndClean($bot);
    }

    protected function humanSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds === 1 ? 'ثانية واحدة' : "{$seconds} ثانية";
        }

        if ($seconds < 3600) {
            $mins = (int) round($seconds / 60);
            if ($mins === 1) return 'دقيقة واحدة';
            if ($mins === 2) return 'دقيقتان';
            if ($mins <= 10) return "{$mins} دقائق";
            return "{$mins} دقيقة";
        }

        if ($seconds < 86400) {
            $hours = (int) round($seconds / 3600);
            if ($hours === 1) return 'ساعة واحدة';
            if ($hours === 2) return 'ساعتان';
            if ($hours <= 10) return "{$hours} ساعات";
            return "{$hours} ساعة";
        }

        $days = (int) round($seconds / 86400);
        if ($days === 1) return 'يوم واحد';
        if ($days === 2) return 'يومان';
        if ($days <= 10) return "{$days} أيام";
        return "{$days} يوماً";
    }

    private function backKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع لأكواد الهدايا',
                    callback_data: 'admin.gift-codes',
                ),
            );
    }
}
