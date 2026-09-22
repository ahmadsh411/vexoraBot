<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\System;

use App\Models\Setting;
use App\Telegram\Keyboards\Base\BaseAdminKeyboard;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class IChancySettingsKeyboard extends BaseAdminKeyboard
{
    public static function make(): InlineKeyboardMarkup
    {
        $k = self::build();

        $enabled = (bool) Setting::get('ichancy.enabled', true);

        // ─── التفعيل/التعطيل ───
        $k = $k->fullButton(
            $enabled ? '🔴 تعطيل IChancy' : '🟢 تفعيل IChancy',
            'sys.ichancy.toggle',
            $enabled ? ButtonStyle::DANGER : ButtonStyle::SUCCESS,
        );

        // ─── حدود الإيداع ───
        $k = $k->fullButton(
            '💰 الحد الأدنى للإيداع',
            'sys.ichancy.edit.ichancy.min_deposit',
            ButtonStyle::PRIMARY,
        );

        $k = $k->fullButton(
            '💰 الحد الأقصى للإيداع',
            'sys.ichancy.edit.ichancy.max_deposit',
            ButtonStyle::PRIMARY,
        );

        // ─── حدود السحب ───
        $k = $k->fullButton(
            '📤 الحد الأدنى للسحب',
            'sys.ichancy.edit.ichancy.min_withdraw',
            ButtonStyle::PRIMARY,
        );

        $k = $k->fullButton(
            '📤 الحد الأقصى للسحب',
            'sys.ichancy.edit.ichancy.max_withdraw',
            ButtonStyle::PRIMARY,
        );

        // ─── سعر العرض ───
        $k = $k->fullButton(
            '💱 سعر العرض (NSP → NPS)',
            'sys.ichancy.edit.ichancy.display_rate',
            ButtonStyle::PRIMARY,
        );

        // ─── رجوع (بدون لون) ───
        return $k
            ->fullButton('⬅️ رجوع للإعدادات', 'admin.system')
            ->toMarkup();
    }
}
