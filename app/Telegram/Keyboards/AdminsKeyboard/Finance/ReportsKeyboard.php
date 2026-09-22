<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Finance;

use App\Telegram\Keyboards\Base\BaseAdminKeyboard;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ReportsKeyboard extends BaseAdminKeyboard
{
    public static function make(): InlineKeyboardMarkup
    {
        return self::build()

            // ─── 📅 فترات قصيرة ───
            ->pairButtons(
                '📅 اليوم',
                'admin.finance.reports.today',
                '📆 آخر 7 أيام',
                'admin.finance.reports.week',
                ButtonStyle::PRIMARY,
                ButtonStyle::PRIMARY,
            )

            // ─── 🗓️ فترات متوسطة ───
            ->pairButtons(
                '🗓️ آخر 30 يوم',
                'admin.finance.reports.month',
                '📅 هذا الشهر',
                'admin.finance.reports.this-month',
                ButtonStyle::PRIMARY,
                ButtonStyle::PRIMARY,
            )

            // ─── 📆 فترات طويلة ───
            ->pairButtons(
                '📅 الشهر الماضي',
                'admin.finance.reports.last-month',
                '📆 هذه السنة',
                'admin.finance.reports.year',
                ButtonStyle::PRIMARY,
                ButtonStyle::PRIMARY,
            )

            // ─── 📊 تقرير شامل ───
            ->fullButton(
                '📊 تقرير شامل',
                'admin.finance.reports.overview',
                ButtonStyle::SUCCESS,
            )

            // ─── ↩️ رجوع (بدون لون) ───
            ->backButton('admin.finance')
            ->toMarkup();
    }
}
