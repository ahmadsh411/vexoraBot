<?php

namespace App\Telegram\Conversations\Admin\Finance;

use App\Models\DepositMethod;
use App\Services\NotificationService;
use App\Telegram\Conversations\BaseConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class CreateDepositMethodConversation extends BaseConversation
{
    public function start(Nutgram $bot): void
    {
        $this->putState($bot, [
            'name'               => null,
            'icon'               => '💰',
            'currency'           => 'NSP',
            'gateway_type'       => null,
            'auto_verify'        => false,
            'receiver_gsm'       => null,
            'receiver_address'   => null,
            'account_number'     => null,
            'account_name'       => null,
            'instructions'       => null,
            'min_amount'         => 10000,
            'max_amount'         => 5000000,
            'commission_percent' => 0,
        ]);

        $this->askTracked(
            $bot,
            implode("\n", [
                '➕ <b>إضافة طريقة إيداع جديدة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📝 أرسل <b>اسم الطريقة</b>:',
                '',
                'مثال: <code>سيرياتيل كاش</code>',
                '',
                'أو /cancel للإلغاء.',
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
            $this->askTracked($bot, '❌ الاسم يجب أن يكون بين 2 و 100 حرف. حاول مرة أخرى:');
            return;
        }

        $state = $this->getState($bot);
        $state['name'] = $text;
        $this->putState($bot, $state);

        $this->askTracked(
            $bot,
            implode("\n", [
                '😀 أرسل <b>إيموجي</b> للطريقة:',
                '',
                'مثال: 📲',
                '',
                'أو /skip للتخطي (الافتراضي: 💰)',
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

        if ($text !== '' && ! str_starts_with($text, '/')) {
            $state = $this->getState($bot);
            $state['icon'] = $text;
            $this->putState($bot, $state);
        }

        $this->askTracked(
            $bot,
            "💰 اختر <b>العملة</b>:",
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '🇸🇾 NSP (ليرة سورية)',
                        callback_data: 'admin.dm.create.currency.NSP',
                        style: ButtonStyle::SUCCESS,
                    ),
                )
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '💵 USD (دولار أمريكي)',
                        callback_data: 'admin.dm.create.currency.USD',
                        style: ButtonStyle::SUCCESS,
                    ),
                ),
        );

        $this->next('askCurrency');
    }

    public function askCurrency(Nutgram $bot): void
    {
        $callbackData = $bot->callbackQuery()?->data;

        if (! $callbackData || ! str_starts_with($callbackData, 'admin.dm.create.currency.')) {
            $this->askTracked($bot, '❌ اختر من الأزرار.');
            return;
        }

        $currency = strtoupper(substr($callbackData, strlen('admin.dm.create.currency.')));

        $state = $this->getState($bot);
        $state['currency'] = $currency;
        $this->putState($bot, $state);

        $bot->answerCallbackQuery();

        $this->askTracked(
            $bot,
            implode("\n", [
                '🔌 اختر <b>نوع البوابة</b>:',
                '',
                '📱 <b>سيرياتيل كاش</b> — تحقق تلقائي عبر GSM',
                '🏦 <b>شام كاش</b> — تحقق تلقائي عبر Address',
                '✋ <b>يدوي</b> — موافقة يدوية من الأدمن',
            ]),
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '📱 سيرياتيل كاش',
                        callback_data: 'admin.dm.create.gateway.syriatel',
                        style: ButtonStyle::PRIMARY,
                    ),
                )
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '🏦 شام كاش',
                        callback_data: 'admin.dm.create.gateway.shamcash',
                        style: ButtonStyle::PRIMARY,
                    ),
                )
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '✋ يدوي',
                        callback_data: 'admin.dm.create.gateway.manual',
                        style: ButtonStyle::DANGER,
                    ),
                ),
        );

        $this->next('askGatewayType');
    }

    public function askGatewayType(Nutgram $bot): void
    {
        $callbackData = $bot->callbackQuery()?->data;

        if (! $callbackData || ! str_starts_with($callbackData, 'admin.dm.create.gateway.')) {
            $this->askTracked($bot, '❌ اختر من الأزرار.');
            return;
        }

        $gatewayType = substr($callbackData, strlen('admin.dm.create.gateway.'));

        if (! in_array($gatewayType, ['syriatel', 'shamcash', 'manual'])) {
            $this->askTracked($bot, '❌ نوع غير صالح.');
            return;
        }

        $state = $this->getState($bot);
        $state['gateway_type'] = $gatewayType;
        $state['auto_verify'] = $gatewayType !== 'manual';
        $this->putState($bot, $state);

        $bot->answerCallbackQuery();

        if ($gatewayType === 'syriatel') {
            $this->askTracked(
                $bot,
                implode("\n", [
                    '📱 <b>سيرياتيل كاش</b>',
                    '',
                    'أرسل <b>رقم GSM المستقبل</b>:',
                    '',
                    'مثال: <code>0931234567</code>',
                    '',
                    'أو /skip للتخطي.',
                ]),
                parse_mode: 'HTML',
            );
            $this->next('askReceiverGsm');
        } elseif ($gatewayType === 'shamcash') {
            $this->askTracked(
                $bot,
                implode("\n", [
                    '🏦 <b>شام كاش</b>',
                    '',
                    'أرسل <b>عنوان شام كاش</b> (Address):',
                    '',
                    'مثال: <code>fad1f67e8583fda13baad7aca1150393</code>',
                    '',
                    'أو /skip للتخطي.',
                ]),
                parse_mode: 'HTML',
            );
            $this->next('askReceiverAddress');
        } else {
            $this->askAccountNumber($bot);
        }
    }

    public function askReceiverGsm(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if ($text !== '' && ! str_starts_with($text, '/')) {
            $state = $this->getState($bot);
            $state['receiver_gsm'] = $text;
            $this->putState($bot, $state);
        }

        $this->askTracked(
            $bot,
            implode("\n", [
                '📞 أرسل <b>رقم الحساب</b> (للعرض):',
                '',
                'مثال: <code>0931234567</code>',
                '',
                'أو /skip للتخطي.',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('askAccountNumber');
    }

    public function askReceiverAddress(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if ($text !== '' && ! str_starts_with($text, '/')) {
            $state = $this->getState($bot);
            $state['receiver_address'] = $text;
            $this->putState($bot, $state);
        }

        $this->askTracked(
            $bot,
            implode("\n", [
                '📞 أرسل <b>رقم الحساب</b> (للعرض):',
                '',
                'مثال: <code>fad1f67e8583fda13baad7aca1150393</code>',
                '',
                'أو /skip للتخطي.',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('askAccountNumber');
    }

    public function askAccountNumber(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if ($text !== '' && ! str_starts_with($text, '/')) {
            $state = $this->getState($bot);
            $state['account_number'] = $text;
            $this->putState($bot, $state);
        }

        $this->askTracked($bot, '👤 أرسل <b>اسم الحساب</b> (أو /skip):', parse_mode: 'HTML');
        $this->next('askAccountName');
    }

    public function askAccountName(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if ($text !== '' && ! str_starts_with($text, '/')) {
            $state = $this->getState($bot);
            $state['account_name'] = $text;
            $this->putState($bot, $state);
        }

        $this->askTracked($bot, '📝 أرسل <b>التعليمات</b> (أو /skip):', parse_mode: 'HTML');
        $this->next('askInstructions');
    }

    public function askInstructions(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if ($text !== '' && ! str_starts_with($text, '/')) {
            $state = $this->getState($bot);
            $state['instructions'] = $text;
            $this->putState($bot, $state);
        }

        $this->askTracked($bot, "📊 أرسل <b>الحد الأدنى</b> (أو /skip = 10,000):", parse_mode: 'HTML');
        $this->next('askMinAmount');
    }

    public function askMinAmount(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if ($text !== '' && ! str_starts_with($text, '/')) {
            $min = (float) str_replace(',', '', $text);
            if ($min >= 0) {
                $state = $this->getState($bot);
                $state['min_amount'] = $min;
                $this->putState($bot, $state);
            }
        }

        $this->askTracked($bot, "📊 أرسل <b>الحد الأقصى</b> (أو /skip = 5,000,000):", parse_mode: 'HTML');
        $this->next('askMaxAmount');
    }

    public function askMaxAmount(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if ($text !== '' && ! str_starts_with($text, '/')) {
            $max = (float) str_replace(',', '', $text);
            if ($max >= 0) {
                $state = $this->getState($bot);
                $state['max_amount'] = $max;
                $this->putState($bot, $state);
            }
        }

        $this->askTracked($bot, "💼 أرسل <b>نسبة العمولة</b> (0-100) أو /skip = 0:", parse_mode: 'HTML');
        $this->next('askCommission');
    }

    public function askCommission(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if ($text !== '' && ! str_starts_with($text, '/')) {
            $commission = (float) str_replace(',', '', $text);
            if ($commission >= 0 && $commission <= 100) {
                $state = $this->getState($bot);
                $state['commission_percent'] = $commission;
                $this->putState($bot, $state);
            }
        }

        $this->save($bot);
    }

    private function save(Nutgram $bot): void
    {
        $state = $this->getState($bot);

        if (empty($state['name'])) {
            $this->keep($bot, '❌ الاسم مطلوب.', reply_markup: $this->backKeyboard());
            $this->clearState($bot);
            $this->endAndClean($bot);
            return;
        }

        try {
            $code = strtolower(str_replace(' ', '_', $state['name']));

            if (DepositMethod::where('code', $code)->exists()) {
                $code = $code . '_' . time();
            }

            $method = DepositMethod::create([
                'name'               => $state['name'],
                'code'               => $code,
                'icon'               => $state['icon'] ?? '💰',
                'currency'           => $state['currency'] ?? 'NSP',
                'gateway_type'       => $state['gateway_type'] ?? 'manual',
                'auto_verify'        => $state['auto_verify'] ?? false,
                'receiver_gsm'       => $state['receiver_gsm'] ?? null,
                'receiver_address'   => $state['receiver_address'] ?? null,
                'account_number'     => $state['account_number'] ?? null,
                'account_name'       => $state['account_name'] ?? null,
                'instructions'       => $state['instructions'] ?? null,
                'min_amount'         => $state['min_amount'] ?? 0,
                'max_amount'         => $state['max_amount'] ?? 0,
                'commission_percent' => $state['commission_percent'] ?? 0,
                'is_active'          => true,
                'sort_order'         => DepositMethod::count() + 1,
            ]);

            try {
                app(NotificationService::class)->notifyDepositMethodChange(
                    bot: $bot,
                    action: 'create',
                    method: $method,
                    adminId: $bot->userId(),
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to notify channel about create', ['error' => $e->getMessage()]);
            }

            try {
                $count = app(NotificationService::class)->notifyAllUsersDepositMethodChange(
                    bot: $bot,
                    action: 'create',
                    method: $method,
                );

                Log::info('Notified users about new deposit method', [
                    'method_id' => $method->id,
                    'count'     => $count,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to notify users about create', ['error' => $e->getMessage()]);
            }

            $this->keep(
                $bot,
                implode("\n", [
                    '✅ <b>تم إضافة الطريقة بنجاح</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🆔 <b>المعرف:</b> <code>' . $method->id . '</code>',
                    $method->icon . ' <b>' . $method->name . '</b>',
                    '💰 <b>العملة:</b> ' . $method->currency_icon . ' ' . $method->currency,
                    '🔌 <b>البوابة:</b> ' . $method->gateway_label,
                    '✅ <b>تحقق تلقائي:</b> ' . ($method->auto_verify ? 'نعم' : 'لا'),
                    '📞 <b>الرقم:</b> <code>' . ($method->account_number ?: '-') . '</code>',
                    '📊 <b>الحدود:</b> ' . $method->limits_label,
                    '💼 <b>العمولة:</b> ' . $method->commission_percent . '%',
                    '',
                    '━━━━━━━━━━━━━━━━━━',
                ]),
                parse_mode: 'HTML',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '👁️ عرض الطريقة',
                            callback_data: "admin.finance.deposit-methods.show.{$method->id}",
                            style: ButtonStyle::PRIMARY,
                        ),
                    )
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '📋 قائمة الطرق',
                            callback_data: 'admin.finance.deposit-methods',
                            style: ButtonStyle::SUCCESS,
                        ),
                    ),
            );
        } catch (\Throwable $e) {
            Log::error('Failed to create deposit method', [
                'error' => $e->getMessage(),
                'data'  => $state,
            ]);

            $this->keep($bot, '❌ فشل الإنشاء: ' . $e->getMessage(), reply_markup: $this->backKeyboard());
        }

        $this->clearState($bot);
        $this->endAndClean($bot);
    }

    private function cancel(Nutgram $bot): void
    {
        $this->keep($bot, '❌ تم الإلغاء.', reply_markup: $this->backKeyboard());
        $this->clearState($bot);
        $this->endAndClean($bot);
    }

    private function backKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع لطرق الإيداع',
                    callback_data: 'admin.finance.deposit-methods',
                ),
            );
    }

    private function stateKey(Nutgram $bot): string
    {
        return 'create_dm_state_' . $bot->userId() . '_' . $bot->chatId();
    }

    private function getState(Nutgram $bot): array
    {
        return Cache::get($this->stateKey($bot), []);
    }

    private function putState(Nutgram $bot, array $state): void
    {
        Cache::put($this->stateKey($bot), $state, now()->addMinutes(30));
    }

    private function clearState(Nutgram $bot): void
    {
        Cache::forget($this->stateKey($bot));
    }
}
