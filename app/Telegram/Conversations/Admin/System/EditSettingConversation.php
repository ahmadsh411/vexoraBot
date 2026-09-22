<?php

namespace App\Telegram\Conversations\Admin\System;

use App\Models\Setting;
use App\Models\User;
use App\Telegram\Conversations\BaseConversation;
use App\Telegram\Screens\Admin\System\SystemScreen;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class EditSettingConversation extends BaseConversation
{
    protected ?string $key = null;

    public function start(Nutgram $bot): void
    {
        $userId = $bot->userId();
        $this->key = cache()->pull("sys.edit.{$userId}");

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
            SystemScreen::askValue($this->key),
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
        if ($setting->type === 'int') {
            if (! is_numeric($text) || (int) $text != $text) {
                $this->askTracked($bot, '⚠️ أرسل رقماً صحيحاً.');
                return;
            }

            if ($setting->key === 'withdraw_fee_percent') {
                $value = (float) $text;
                if ($value < 0 || $value > 100) {
                    $this->askTracked($bot, '⚠️ النسبة بين 0 و 100.');
                    return;
                }
            }
        }

        if ($setting->type === 'decimal' && ! is_numeric($text)) {
            $this->askTracked($bot, '⚠️ أرسل رقماً.');
            return;
        }

        if ($setting->type === 'bool') {
            $text = in_array(strtolower($text), ['1', 'true', 'نعم', 'on', 'yes'])
                ? '1'
                : '0';
        }

        $admin = User::where('telegram_id', $bot->userId())->first();

        app(\App\Services\SettingService::class)->setByAdmin(
            key: $this->key,
            value: $text,
            admin: $admin,
            reason: 'Edit from conversation',
        );

        // ✅ رسالة النجاح — تبقى + زر رجوع
        $this->keep(
            $bot,
            implode("\n", [
                '✅ <b>تم الحفظ</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📝 <b>' . $setting->label . '</b>',
                '<code>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</code>',
            ]),
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
                    text: '⬅️ رجوع للإعدادات',
                    callback_data: 'sys.general',
                ),
            );
    }
}
