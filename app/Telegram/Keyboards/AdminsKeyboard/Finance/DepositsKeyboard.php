<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Finance;

use App\Models\Transaction;
use App\Telegram\Keyboards\Base\BaseAdminKeyboard;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class DepositsKeyboard extends BaseAdminKeyboard
{
    public static function make(): InlineKeyboardMarkup
    {
        // ─── الإحصائيات ───
        $approvedToday = Transaction::completed()
            ->deposits()
            ->whereDate('completed_at', today())
            ->count();

        $rejectedToday = Transaction::where('status', Transaction::STATUS_REJECTED)
            ->deposits()
            ->whereDate('rejected_at', today())
            ->count();

        $approvedLabel = $approvedToday > 0
            ? "🟢 المعتمدة ({$approvedToday})"
            : '🟢 المعتمدة';

        $rejectedLabel = $rejectedToday > 0
            ? "🔴 المرفوضة ({$rejectedToday})"
            : '🔴 المرفوضة';

        return self::build()

            // ─── 🟢 المعتمدة + 🔴 المرفوضة ───
            ->pairButtons(
                $approvedLabel,
                'admin.finance.deposits.approved',
                $rejectedLabel,
                'admin.finance.deposits.rejected',
                ButtonStyle::SUCCESS,
                ButtonStyle::DANGER,
            )

            // ─── 📜 كل الطلبات ───
            ->fullButton(
                '📜 كل الطلبات',
                'admin.finance.deposits.all',
                ButtonStyle::PRIMARY,
            )

            // ─── ↩️ رجوع (بدون لون) ───
            ->backButton('admin.finance')
            ->toMarkup();
    }
}
