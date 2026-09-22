<?php

namespace App\Telegram\Conversations\Admin\Finance;

use App\Models\DepositMethod;
use App\Services\NotificationService;
use App\Telegram\Conversations\BaseConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class EditDepositMethodConversation extends BaseConversation
{
    public function start(Nutgram $bot): void
    {
        $cacheKey = 'edit_dm_' . $bot->userId() . '_' . $bot->chatId();
        $payload = Cache::pull($cacheKey);

        if (! $payload || ! isset($payload['method_id'], $payload['field'])) {
            $this->keep($bot, '❌ انتهت الجلسة. حاول مرة أخرى.', reply_markup: $this->backKeyboard(null));
            $this->endAndClean($bot);
            return;
        }

        $stateKey = $this->stateKey($bot);
        Cache::put($stateKey, $payload, now()->addMinutes(30));

        $method = DepositMethod::find($payload['method_id']);

        if (! $method) {
            $this->keep($bot, '❌ الطريقة غير موجودة.', reply_markup: $this->backKeyboard(null));
            $this->endAndClean($bot);
            return;
        }

        match ($payload['field']) {
            'name'             => $this->askName($bot, $method),
            'icon'             => $this->askIcon($bot, $method),
            'account_number'   => $this->askAccountNumber($bot, $method),
            'account_name'     => $this->askAccountName($bot, $method),
            'instructions'     => $this->askInstructions($bot, $method),
            'limits'           => $this->askMinAmount($bot, $method),
            'commission'       => $this->askCommission($bot, $method),
            'receiver_gsm'     => $this->askReceiverGsm($bot, $method),
            'receiver_address' => $this->askReceiverAddress($bot, $method),
            default            => $this->endAndClean($bot),
        };
    }

    private function askName(Nutgram $bot, DepositMethod $method): void
    {
        $this->askTracked($bot,
            "✏️ <b>تعديل الاسم</b>\n\n" .
            "الاسم الحالي: <b>{$method->name}</b>\n\n" .
            "أرسل الاسم الجديد (أو /cancel):",
            parse_mode: 'HTML',
        );
        $this->next('saveName');
    }

    public function saveName(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel' || $text === '') {
            $this->cancel($bot);
            return;
        }

        if (mb_strlen($text) < 2 || mb_strlen($text) > 100) {
            $this->askTracked($bot, '❌ الاسم يجب أن يكون بين 2 و 100 حرف. حاول مرة أخرى:');
            return;
        }

        $this->updateField($bot, 'name', $text);
    }

    private function askIcon(Nutgram $bot, DepositMethod $method): void
    {
        $this->askTracked($bot,
            "😀 <b>تعديل الأيقونة</b>\n\n" .
            "الأيقونة الحالية: {$method->icon}\n\n" .
            "أرسل إيموجي واحد (أو /cancel):",
            parse_mode: 'HTML',
        );
        $this->next('saveIcon');
    }

    public function saveIcon(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel' || $text === '') {
            $this->cancel($bot);
            return;
        }

        if (mb_strlen($text) > 5) {
            $this->askTracked($bot, '❌ أرسل إيموجي واحد فقط. حاول مرة أخرى:');
            return;
        }

        $this->updateField($bot, 'icon', $text);
    }

    private function askAccountNumber(Nutgram $bot, DepositMethod $method): void
    {
        $this->askTracked($bot,
            "📞 <b>تعديل رقم الحساب (للعرض)</b>\n\n" .
            "الحالي: <code>" . ($method->account_number ?: 'غير محدد') . "</code>\n\n" .
            "أرسل الرقم الجديد (أو /cancel):",
            parse_mode: 'HTML',
        );
        $this->next('saveAccountNumber');
    }

    public function saveAccountNumber(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        $this->updateField($bot, 'account_number', $text);
    }

    private function askAccountName(Nutgram $bot, DepositMethod $method): void
    {
        $this->askTracked($bot,
            "👤 <b>تعديل اسم الحساب</b>\n\n" .
            "الحالي: <code>" . ($method->account_name ?: 'غير محدد') . "</code>\n\n" .
            "أرسل الاسم الجديد (أو /cancel):",
            parse_mode: 'HTML',
        );
        $this->next('saveAccountName');
    }

    public function saveAccountName(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        $this->updateField($bot, 'account_name', $text);
    }

    private function askReceiverGsm(Nutgram $bot, DepositMethod $method): void
    {
        $this->askTracked($bot,
            implode("\n", [
                '📱 <b>تعديل GSM المستقبل (سيرياتيل)</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                'الحالي: <code>' . ($method->receiver_gsm ?: 'غير محدد') . '</code>',
                '',
                '📝 أرسل <b>رقم GSM</b> الجديد:',
                '',
                '💡 يجب أن يبدأ بـ <code>09</code> ويتكون من 10 أرقام',
                'مثال: <code>0933000000</code>',
                '',
                '⚠️ لإفراغ الحقل أرسل: <code>-</code>',
                '',
                '↩️ أو /cancel للإلغاء.',
            ]),
            parse_mode: 'HTML',
        );
        $this->next('saveReceiverGsm');
    }

    public function saveReceiverGsm(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if ($text === '-') {
            $this->updateField($bot, 'receiver_gsm', null);
            return;
        }

        if (! preg_match('/^09\d{8}$/', $text)) {
            $this->askTracked($bot,
                "❌ <b>رقم GSM غير صالح</b>\n\n" .
                "يجب أن يبدأ بـ <code>09</code> ويتكون من 10 أرقام.\n" .
                "مثال: <code>0933000000</code>\n\n" .
                "حاول مرة أخرى (أو /cancel):",
                parse_mode: 'HTML',
            );
            return;
        }

        $this->updateField($bot, 'receiver_gsm', $text);
    }

    private function askReceiverAddress(Nutgram $bot, DepositMethod $method): void
    {
        $current = $method->receiver_address ?: 'غير محدد';

        $this->askTracked($bot,
            implode("\n", [
                '🏦 <b>تعديل عنوان محفظة شام كاش</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                'الحالي:',
                '<code>' . $current . '</code>',
                '',
                '📝 أرسل <b>عنوان المحفظة</b> الجديد:',
                '',
                '💡 سلسلة hex طويلة (32-64 حرفاً)',
                'مثال: <code>06ff99d12f3b34d7956e3caaf756873e</code>',
                '',
                '⚠️ لإفراغ الحقل أرسل: <code>-</code>',
                '',
                '↩️ أو /cancel للإلغاء.',
            ]),
            parse_mode: 'HTML',
        );
        $this->next('saveReceiverAddress');
    }

    public function saveReceiverAddress(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if ($text === '-') {
            $this->updateField($bot, 'receiver_address', null);
            return;
        }

        if (! preg_match('/^[a-f0-9]{20,}$/i', $text)) {
            $this->askTracked($bot,
                "❌ <b>عنوان غير صالح</b>\n\n" .
                "يجب أن يكون سلسلة hex طويلة (20+ حرفاً).\n" .
                "مثال: <code>06ff99d12f3b34d7956e3caaf756873e</code>\n\n" .
                "حاول مرة أخرى (أو /cancel):",
                parse_mode: 'HTML',
            );
            return;
        }

        $this->updateField($bot, 'receiver_address', strtolower($text));
    }

    private function askInstructions(Nutgram $bot, DepositMethod $method): void
    {
        $this->askTracked($bot,
            "📝 <b>تعديل التعليمات</b>\n\n" .
            "الحالية:\n" . ($method->instructions ?: '-') . "\n\n" .
            "أرسل التعليمات الجديدة (أو /cancel):",
            parse_mode: 'HTML',
        );
        $this->next('saveInstructions');
    }

    public function saveInstructions(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel' || $text === '') {
            $this->cancel($bot);
            return;
        }

        $this->updateField($bot, 'instructions', $text);
    }

    private function askMinAmount(Nutgram $bot, DepositMethod $method): void
    {
        $this->askTracked($bot,
            "📊 <b>تعديل الحدود</b>\n\n" .
            "الحالية: <b>{$method->limits_label}</b>\n\n" .
            "📝 أرسل <b>الحد الأدنى</b> (أو /cancel):",
            parse_mode: 'HTML',
        );
        $this->next('saveMinAmount');
    }

    public function saveMinAmount(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        $min = (float) str_replace(',', '', $text);

        if ($min < 0) {
            $this->askTracked($bot, '❌ قيمة غير صالحة. حاول مرة أخرى:');
            return;
        }

        $state = $this->getState($bot);
        $state['min_amount'] = $min;
        $this->putState($bot, $state);

        $this->askTracked($bot, "📊 أرسل <b>الحد الأقصى</b> (أو /cancel):", parse_mode: 'HTML');
        $this->next('saveMaxAmount');
    }

    public function saveMaxAmount(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        $max = (float) str_replace(',', '', $text);

        if ($max < 0) {
            $this->askTracked($bot, '❌ قيمة غير صالحة. حاول مرة أخرى:');
            return;
        }

        $state = $this->getState($bot);
        $min = $state['min_amount'] ?? 0;

        if ($max > 0 && $min > $max) {
            $this->askTracked($bot, '❌ الحد الأدنى أكبر من الأقصى. حاول مرة أخرى:');
            return;
        }

        $this->updateFields($bot, [
            'min_amount' => $min,
            'max_amount' => $max,
        ]);
    }

    private function askCommission(Nutgram $bot, DepositMethod $method): void
    {
        $this->askTracked($bot,
            "💼 <b>تعديل العمولة</b>\n\n" .
            "الحالية: <b>{$method->commission_percent}%</b>\n\n" .
            "📝 أرسل النسبة الجديدة (0-100) أو /cancel:",
            parse_mode: 'HTML',
        );
        $this->next('saveCommission');
    }

    public function saveCommission(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        $commission = (float) str_replace(',', '', $text);

        if ($commission < 0 || $commission > 100) {
            $this->askTracked($bot, '❌ النسبة يجب أن تكون بين 0 و 100. حاول مرة أخرى:');
            return;
        }

        $this->updateField($bot, 'commission_percent', $commission);
    }

    private function updateField(Nutgram $bot, string $field, mixed $value): void
    {
        $state = $this->getState($bot);
        $method = DepositMethod::find($state['method_id'] ?? null);

        if (! $method) {
            $this->keep($bot, '❌ الطريقة غير موجودة.', reply_markup: $this->backKeyboard(null));
            $this->clearState($bot);
            $this->endAndClean($bot);
            return;
        }

        $oldValue = $method->{$field};

        $method->update([$field => $value]);
        $method->refresh();

        try {
            app(NotificationService::class)->notifyDepositMethodChange(
                bot: $bot,
                action: 'update',
                method: $method,
                changes: [$field => ['old' => $oldValue, 'new' => $value]],
                adminId: $bot->userId(),
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about update', ['error' => $e->getMessage()]);
        }

        try {
            $count = app(NotificationService::class)->notifyAllUsersDepositMethodChange(
                bot: $bot,
                action: 'update',
                method: $method,
                changes: [$field => ['old' => $oldValue, 'new' => $value]],
            );

            Log::info('Notified users about deposit method update', [
                'method_id' => $method->id,
                'field'     => $field,
                'count'     => $count,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify users about update', ['error' => $e->getMessage()]);
        }

        $fieldLabel = match ($field) {
            'name'             => 'الاسم',
            'icon'             => 'الأيقونة',
            'account_number'   => 'رقم الحساب',
            'account_name'     => 'اسم الحساب',
            'instructions'     => 'التعليمات',
            'commission_percent' => 'العمولة',
            'receiver_gsm'     => 'GSM المستقبل',
            'receiver_address' => 'عنوان شام كاش',
            default            => $field,
        };

        $displayValue = $value === null ? '(فارغ)' : htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        $this->keep(
            $bot,
            "✅ <b>تم التحديث بنجاح</b>\n\n" .
            "📝 الحقل: <b>{$fieldLabel}</b>\n" .
            "💾 القيمة الجديدة: <code>{$displayValue}</code>",
            parse_mode: 'HTML',
            reply_markup: $this->backKeyboard($method->id),
        );

        $this->clearState($bot);
        $this->endAndClean($bot);
    }

    private function updateFields(Nutgram $bot, array $fields): void
    {
        $state = $this->getState($bot);
        $method = DepositMethod::find($state['method_id'] ?? null);

        if (! $method) {
            $this->keep($bot, '❌ الطريقة غير موجودة.', reply_markup: $this->backKeyboard(null));
            $this->clearState($bot);
            $this->endAndClean($bot);
            return;
        }

        $changes = [];
        foreach ($fields as $field => $newValue) {
            $changes[$field] = ['old' => $method->{$field}, 'new' => $newValue];
        }

        $method->update($fields);
        $method->refresh();

        try {
            app(NotificationService::class)->notifyDepositMethodChange(
                bot: $bot,
                action: 'update',
                method: $method,
                changes: $changes,
                adminId: $bot->userId(),
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about update limits', ['error' => $e->getMessage()]);
        }

        try {
            $count = app(NotificationService::class)->notifyAllUsersDepositMethodChange(
                bot: $bot,
                action: 'update',
                method: $method,
                changes: $changes,
            );

            Log::info('Notified users about deposit method limits update', [
                'method_id' => $method->id,
                'count'     => $count,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify users about update limits', ['error' => $e->getMessage()]);
        }

        $this->keep(
            $bot,
            "✅ <b>تم التحديث بنجاح</b>\n\n" .
            "📊 الحدود الجديدة: <b>{$method->limits_label}</b>",
            parse_mode: 'HTML',
            reply_markup: $this->backKeyboard($method->id),
        );

        $this->clearState($bot);
        $this->endAndClean($bot);
    }

    private function cancel(Nutgram $bot): void
    {
        $state = $this->getState($bot);
        $methodId = $state['method_id'] ?? null;

        $this->keep(
            $bot,
            '❌ تم الإلغاء.',
            reply_markup: $this->backKeyboard($methodId),
        );

        $this->clearState($bot);
        $this->endAndClean($bot);
    }

    /**
     * 🔙 زر الرجوع
     */
    private function backKeyboard(?int $methodId): InlineKeyboardMarkup
    {
        $button = $methodId
            ? InlineKeyboardButton::make(
                text: '⬅️ رجوع لتفاصيل الطريقة',
                callback_data: "admin.finance.deposit-methods.show.{$methodId}",
            )
            : InlineKeyboardButton::make(
                text: '⬅️ رجوع لطرق الإيداع',
                callback_data: 'admin.finance.deposit-methods',
            );

        return InlineKeyboardMarkup::make()->addRow($button);
    }

    private function stateKey(Nutgram $bot): string
    {
        return 'edit_dm_state_' . $bot->userId() . '_' . $bot->chatId();
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
