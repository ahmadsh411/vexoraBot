<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Referrals;

use App\Models\ReferralSetting;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ReferralSettingsKeyboard
{
    public const STEP_PERCENT = 0.5;
    public const STEP_DAYS = 1;

    public static function make(): InlineKeyboardMarkup
    {
        $settings = ReferralSetting::current();

        return InlineKeyboardMarkup::make()

            // ─── ⚡ فوري L1 ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⚡ فوري L1 — ' . $settings->instant_level_1_percent . '%',
                    callback_data: 'admin.referrals.settings.noop',
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '➖ ' . self::STEP_PERCENT . '%',
                    callback_data: 'admin.referrals.settings.instant-l1.dec',
                    style: ButtonStyle::DANGER,
                ),
                InlineKeyboardButton::make(
                    text: '➕ ' . self::STEP_PERCENT . '%',
                    callback_data: 'admin.referrals.settings.instant-l1.inc',
                    style: ButtonStyle::SUCCESS,
                ),
            )

            // ─── ⚡ فوري L2 ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⚡ فوري L2 — ' . $settings->instant_level_2_percent . '%',
                    callback_data: 'admin.referrals.settings.noop',
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '➖ ' . self::STEP_PERCENT . '%',
                    callback_data: 'admin.referrals.settings.instant-l2.dec',
                    style: ButtonStyle::DANGER,
                ),
                InlineKeyboardButton::make(
                    text: '➕ ' . self::STEP_PERCENT . '%',
                    callback_data: 'admin.referrals.settings.instant-l2.inc',
                    style: ButtonStyle::SUCCESS,
                ),
            )

            // ─── 🔄 دوري L1 ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔄 دوري L1 — ' . $settings->cycle_level_1_percent . '%',
                    callback_data: 'admin.referrals.settings.noop',
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '➖ ' . self::STEP_PERCENT . '%',
                    callback_data: 'admin.referrals.settings.cycle-l1.dec',
                    style: ButtonStyle::DANGER,
                ),
                InlineKeyboardButton::make(
                    text: '➕ ' . self::STEP_PERCENT . '%',
                    callback_data: 'admin.referrals.settings.cycle-l1.inc',
                    style: ButtonStyle::SUCCESS,
                ),
            )

            // ─── 🔄 دوري L2 ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔄 دوري L2 — ' . $settings->cycle_level_2_percent . '%',
                    callback_data: 'admin.referrals.settings.noop',
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '➖ ' . self::STEP_PERCENT . '%',
                    callback_data: 'admin.referrals.settings.cycle-l2.dec',
                    style: ButtonStyle::DANGER,
                ),
                InlineKeyboardButton::make(
                    text: '➕ ' . self::STEP_PERCENT . '%',
                    callback_data: 'admin.referrals.settings.cycle-l2.inc',
                    style: ButtonStyle::SUCCESS,
                ),
            )

            // ─── 📅 مدة الدورة ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📅 مدة الدورة — ' . $settings->cycle_days . ' يوم',
                    callback_data: 'admin.referrals.settings.noop',
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '➖ ' . self::STEP_DAYS . ' يوم',
                    callback_data: 'admin.referrals.settings.cycle-days.dec',
                    style: ButtonStyle::DANGER,
                ),
                InlineKeyboardButton::make(
                    text: '➕ ' . self::STEP_DAYS . ' يوم',
                    callback_data: 'admin.referrals.settings.cycle-days.inc',
                    style: ButtonStyle::SUCCESS,
                ),
            )

            // ─── 🔧 الحالة ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: $settings->is_active ? '🟢 النظام مفعّل' : '🔴 النظام معطّل',
                    callback_data: 'admin.referrals.settings.toggle-active',
                    style: $settings->is_active ? ButtonStyle::SUCCESS : ButtonStyle::DANGER,
                ),
            )

            // ─── رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'admin.referrals',
                ),
            );
    }
}
