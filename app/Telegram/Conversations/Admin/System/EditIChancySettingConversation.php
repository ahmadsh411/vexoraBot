<?php

namespace App\Telegram\Conversations\Admin\System;

use App\Models\Setting;
use App\Telegram\Conversations\BaseConversation;
use App\Telegram\Screens\Admin\System\IChancySettingsScreen;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class EditIChancySettingConversation extends BaseConversation
{
    protected ?string $key = null;

    public function start(Nutgram $bot): void
    {
        $this->key = Cache::pull("ichancy.edit.{$bot->userId()}");

        if (! $this->key) {
            $this->keep(
                $bot,
                '⚠️ انتهت صلاحية العملية.',
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        $setting = Setting::where('key', $this->key)->first();

        if (! $setting || ! $setting->is_editable) {
            $this->keep(
                $bot,
                '🔒 هذا الإعداد غير قابل للتعديل.',
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        $this->askTracked(
            $bot,
            IChancySettingsScreen::askValue($this->key),
            parse_mode: 'HTML',
        );

        $this->next('receiveValue');
    }

    public function receiveValue(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '' || $text === '/cancel') {
            $this->keep(
                $bot,
                '❌ تم الإلغاء.',
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        $setting = Setting::where('key', $this->key)->first();

        if (! $setting) {
            $this->keep(
                $bot,
                '⚠️ الإعداد غير موجود.',
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        // ✅ التحقق حسب النوع
        if ($setting->type === Setting::TYPE_INT) {
            if (! is_numeric($text)) {
                $this->askTracked($bot, '⚠️ أرسل رقماً صحيحاً فقط.');
                return;
            }

            $value = (int) $text;

            if ($value < 0) {
                $this->askTracked($bot, '⚠️ القيمة يجب أن تكون موجبة.');
                return;
            }
        } elseif ($setting->type === Setting::TYPE_BOOL) {
            if (! in_array($text, ['0', '1', 'true', 'false'], true)) {
                $this->askTracked($bot, '⚠️ أرسل 1 أو 0.');
                return;
            }

            $value = in_array($text, ['1', 'true'], true) ? '1' : '0';
        } else {
            $value = $text;
        }

        // ✅ حفظ
        $setting->update(['value' => (string) $value]);
        cache()->forget("setting.{$setting->key}");

        Log::info('IChancy setting updated', [
            'admin_id' => $bot->userId(),
            'key'      => $setting->key,
            'value'    => $value,
        ]);

        // ✅ رسالة النجاح — تبقى + زر رجوع
        $this->keep(
            $bot,
            "✅ تم تحديث <b>{$setting->label}</b>\n\nالقيمة الجديدة: <code>{$value}</code>",
            parse_mode: 'HTML',
            reply_markup: $this->backKeyboard(),
        );

        $this->endAndClean($bot);
    }

    /**
     * 🔙 زر الرجوع الموحد
     */
    private function backKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع لإعدادات iChancy',
                    callback_data: 'sys.ichancy',
                ),
            );
    }
}
