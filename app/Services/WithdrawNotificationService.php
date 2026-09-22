<?php

namespace App\Services;

use App\Models\DepositMethod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class WithdrawNotificationService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    // ============================================================
    //  إشعار السوبر أدمن (مباشر + قناة)
    // ============================================================

    public function notifySuperAdmins(
        Nutgram $bot,
        Transaction $transaction,
        User $user,
        DepositMethod $method,
    ): void {
        $text     = $this->buildAdminNotification($transaction, $user, $method);
        $keyboard = $this->adminActionsKeyboard($transaction);

        // ✅ إشعار مباشر
        $superAdmins = User::where('is_admin', true)
            ->where('is_super_admin', true)
            ->where('is_active', true)
            ->whereNotNull('telegram_id')
            ->get();

        foreach ($superAdmins as $admin) {
            try {
                $bot->sendMessage(
                    text: $text,
                    chat_id: $admin->telegram_id,
                    parse_mode: 'HTML',
                    reply_markup: $keyboard,
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to notify super admin', [
                    'admin_id'       => $admin->id,
                    'transaction_id' => $transaction->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        // ✅ إشعار القناة
        try {
            $this->notifications->notifyTransactionsChannel(
                $bot,
                $this->buildChannelNotification($transaction, $user, $method),
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about withdraw', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  نصوص الإشعارات
    // ============================================================

    private function buildAdminNotification(
        Transaction $transaction,
        User $user,
        DepositMethod $method,
    ): string {
        $telegramLink = '<a href="tg://user?id=' . $user->telegram_id . '">'
            . '<code>' . $user->telegram_id . '</code></a>';

        $meta       = $transaction->metadata ?? [];
        $feePercent = $meta['fee_percent'] ?? 0;
        $feeAmount  = $meta['fee_amount'] ?? 0;
        $total      = $meta['total_deducted'] ?? 0;
        $destName   = $meta['destination_name'] ?? null;

        $lines = [
            '🔔 <b>طلب سحب جديد</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🆔 <b>العملية:</b> <code>#' . $transaction->id . '</code>',
            '🔖 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
            '',
            '👤 <b>المستخدم:</b>',
            '└── <code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
            '└── ' . $telegramLink,
            '',
            '💳 <b>الطريقة:</b> ' . $method->full_name,
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>المبلغ:</b> <b>' . number_format((float) $transaction->amount_from, 2) . '</b> ' . $method->currency,
            '💼 <b>العمولة (' . $feePercent . '%):</b> <b>' . number_format((float) $feeAmount, 2) . '</b>',
            '💳 <b>المخصوم:</b> <b>' . number_format((float) $total, 2) . '</b> ' . $method->currency,
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎯 <b>الاستلام:</b>',
            '└── <code>' . htmlspecialchars((string) $transaction->user_account_number, ENT_QUOTES, 'UTF-8') . '</code>',
        ];

        if ($destName) {
            $lines[] = '└── ' . htmlspecialchars($destName, ENT_QUOTES, 'UTF-8');
        }

        $lines[] = '';
        $lines[] = '📅 <b>التاريخ:</b> ' . $transaction->created_at?->format('Y-m-d H:i');
        $lines[] = '';
        $lines[] = '⚡ <b>اختر إجراء:</b>';

        return implode("\n", $lines);
    }

    private function buildChannelNotification(
        Transaction $transaction,
        User $user,
        DepositMethod $method,
    ): string {
        $telegramLink = '<a href="tg://user?id=' . $user->telegram_id . '">'
            . '<code>' . $user->telegram_id . '</code></a>';

        return implode("\n", [
            '📤 <b>طلب سحب جديد</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🆔 <b>العملية:</b> <code>#' . $transaction->id . '</code>',
            '',
            '👤 <b>المستخدم:</b>',
            '└── <code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
            '└── ' . $telegramLink,
            '',
            '💳 <b>الطريقة:</b> ' . $method->full_name,
            '📊 <b>المبلغ:</b> <b>' . number_format((float) $transaction->amount_from, 2) . '</b> ' . $method->currency,
            '',
            '📊 <b>الحالة:</b> 🟡 معلّق',
            '',
            '📅 ' . $transaction->created_at?->format('Y-m-d H:i'),
        ]);
    }

    private function adminActionsKeyboard(Transaction $transaction): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✅ موافقة وإرسال',
                    callback_data: "admin.finance.withdraws.approve.{$transaction->id}",
                ),
                InlineKeyboardButton::make(
                    text: '❌ رفض',
                    callback_data: "admin.finance.withdraws.reject.{$transaction->id}",
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '👁️ عرض التفاصيل',
                    callback_data: "admin.finance.withdraws.show.{$transaction->id}",
                ),
            );
    }
}
