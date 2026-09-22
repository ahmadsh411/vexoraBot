<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Referrals;

use App\Models\ReferralCycle;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ReferralCyclesKeyboard
{
    public static function make(): InlineKeyboardMarkup
    {
        $currentCycle = ReferralCycle::open()->latestFirst()->first();

        $keyboard = InlineKeyboardMarkup::make();

        if ($currentCycle) {
            // ─── الدورة الحالية ───
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '👁️ الدورة الحالية',
                    callback_data: "admin.referrals.cycles.show.{$currentCycle->id}",
                    style: ButtonStyle::PRIMARY,
                ),
            );

            // ─── إغلاق ───
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🔒 إغلاق الدورة الحالية',
                    callback_data: "admin.referrals.cycles.close.{$currentCycle->id}",
                    style: ButtonStyle::DANGER,
                ),
            );
        }

        // ─── الدورات السابقة ───
        $closedCount = ReferralCycle::closed()->count();

        if ($closedCount > 0) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: "📜 الدورات السابقة ({$closedCount})",
                    callback_data: 'admin.referrals.cycles.list',
                    style: ButtonStyle::PRIMARY,
                ),
            );
        }

        // ─── إنشاء دورة جديدة ───
        if (! $currentCycle) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🆕 إنشاء دورة جديدة',
                    callback_data: 'admin.referrals.cycles.create',
                    style: ButtonStyle::SUCCESS,
                ),
            );
        }

        // ─── رجوع (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '↩️ رجوع',
                callback_data: 'admin.referrals',
            ),
        );

        return $keyboard;
    }

    public static function details(int $cycleId): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🏆 مكافآت الدورة',
                    callback_data: "admin.referrals.cycles.rewards.{$cycleId}",
                    style: ButtonStyle::SUCCESS,
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'admin.referrals.cycles',
                ),
            );
    }

    public static function confirmClose(int $cycleId): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✅ نعم، أغلق',
                    callback_data: "admin.referrals.cycles.close-confirm.{$cycleId}",
                    style: ButtonStyle::DANGER,
                ),
                InlineKeyboardButton::make(
                    text: '✖️ إلغاء',
                    callback_data: 'admin.referrals.cycles',
                ),
            );
    }

    public static function list(): InlineKeyboardMarkup
    {
        $cycles = ReferralCycle::closed()
            ->latestFirst()
            ->limit(10)
            ->get();

        $keyboard = InlineKeyboardMarkup::make();

        foreach ($cycles as $cycle) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '📅 ' . $cycle->start_date->format('m-d') . ' ← ' . $cycle->end_date->format('m-d'),
                    callback_data: "admin.referrals.cycles.show.{$cycle->id}",
                    style: ButtonStyle::PRIMARY,
                ),
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '↩️ رجوع',
                callback_data: 'admin.referrals.cycles',
            ),
        );

        return $keyboard;
    }

    public static function rewards(int $cycleId): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: "admin.referrals.cycles.show.{$cycleId}",
                ),
            );
    }
}
