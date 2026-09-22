<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Finance;

use App\Models\Transaction;
use App\Telegram\Keyboards\Base\BaseAdminKeyboard;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class WithdrawsKeyboard extends BaseAdminKeyboard
{
    public static function make(): InlineKeyboardMarkup
    {
        // ─── الإحصائيات ───
        $pendingCount = Transaction::pending()->withdrawals()->count();

        $approvedToday = Transaction::completed()
            ->withdrawals()
            ->whereDate('completed_at', today())
            ->count();

        $rejectedToday = Transaction::where('status', Transaction::STATUS_REJECTED)
            ->withdrawals()
            ->whereDate('rejected_at', today())
            ->count();

        // ─── التسميات ───
        $pendingLabel = $pendingCount > 0
            ? "🟡 المعلقة ({$pendingCount})"
            : '🟡 المعلقة';

        $approvedLabel = $approvedToday > 0
            ? "🟢 المعتمدة ({$approvedToday})"
            : '🟢 المعتمدة';

        $rejectedLabel = $rejectedToday > 0
            ? "🔴 المرفوضة ({$rejectedToday})"
            : '🔴 المرفوضة';

        return self::build()

            // ─── 🟡 المعلقة (الأهم) ───
            ->fullButton(
                $pendingLabel,
                'admin.finance.withdraws.pending',
                ButtonStyle::PRIMARY,
            )

            // ─── 🟢 المعتمدة + 🔴 المرفوضة ───
            ->pairButtons(
                $approvedLabel,
                'admin.finance.withdraws.approved',
                $rejectedLabel,

                'admin.finance.withdraws.rejected',
                ButtonStyle::SUCCESS,
                ButtonStyle::DANGER,
            )

            // ─── 📜 كل الطلبات ───
            ->fullButton(
                '📜 كل الطلبات',
                'admin.finance.withdraws.all',
                ButtonStyle::PRIMARY,
            )

            // ─── ↩️ رجوع ───
            ->backButton('admin.finance')
            ->toMarkup();
    }

    // ============================================================
    //  👁️ كيبورد تفاصيل طلب (للمعلقة فقط)
    // ============================================================
    public static function detailsKeyboard(Transaction $transaction): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        // ─── ✅ إذا الطلب معلّق → موافقة/رفض ───
        if ($transaction->isPending()) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '✅ موافقة وإرسال',
                    callback_data: "admin.finance.withdraws.approve.{$transaction->id}",
                    style: ButtonStyle::SUCCESS,
                ),
            );

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '❌ رفض',
                    callback_data: "admin.finance.withdraws.reject.{$transaction->id}",
                    style: ButtonStyle::DANGER,
                ),
            );
        }

        // ─── ↩️ رجوع (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '⬅️ رجوع',
                callback_data: 'admin.finance.withdraws',
            ),
        );

        return $keyboard;
    }
}
