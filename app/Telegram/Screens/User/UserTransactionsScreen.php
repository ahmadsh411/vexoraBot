<?php

namespace App\Telegram\Screens\User;

use App\Models\Transaction;
use App\Models\User;

class UserTransactionsScreen
{
    // ============================================================
    //  📊 الرئيسية
    // ============================================================

    public static function main(User $user): string
    {
        $totalDeposits = (float) Transaction::completed()
            ->deposits()
            ->where('user_id', $user->id)
            ->sum('amount_to');

        $totalWithdrawals = (float) Transaction::completed()
            ->withdrawals()
            ->where('user_id', $user->id)
            ->sum('amount_from');

        $total = Transaction::where('user_id', $user->id)->count();
        $pending = Transaction::pending()->where('user_id', $user->id)->count();

        $net = $totalDeposits - $totalWithdrawals;

        return implode("\n", [
            '📜 <b>السجل المالي</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>ملخص:</b>',
            '├── 📥 <b>إجمالي الإيداعات:</b>',
            '│   └── <b>' . number_format($totalDeposits, 0) . '</b> NSP (ليرة سورية جديدة)',
            '│',
            '├── 📤 <b>إجمالي السحوبات:</b>',
            '│   └── <b>' . number_format($totalWithdrawals, 0) . '</b> NSP (ليرة سورية جديدة)',
            '│',
            '├── 📊 <b>الصافي:</b>',
            '│   └── <b>' . number_format($net, 0) . '</b> NSP (ليرة سورية جديدة)',
            '│',
            '├── 📋 <b>عدد العمليات:</b> <b>' . number_format($total) . '</b>',
            '│',
            '└── 🟡 <b>المعلقة:</b> <b>' . number_format($pending) . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📅 <i>اختر الفترة:</i>',
        ]);
    }

    // ============================================================
    //  📄 القائمة
    // ============================================================

    public static function list(User $user, string $filter = 'all', int $page = 1): string
    {
        $perPage = 10;
        $transactions = self::getFiltered($user, $filter, $page, $perPage);
        $total = self::countFiltered($user, $filter);

        if ($transactions->isEmpty()) {
            return implode("\n", [
                '📜 <b>السجل المالي</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📭 <b>لا توجد عمليات</b>',
            ]);
        }

        $filterLabel = self::filterLabel($filter);
        $totalPages = ceil($total / $perPage);

        $lines = [
            '📜 <b>' . $filterLabel . '</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الإجمالي:</b> ' . number_format($total),
            '📄 <b>الصفحة:</b> ' . $page . '/' . $totalPages,
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
        ];

        foreach ($transactions as $tx) {
            $lines[] = self::formatTransaction($tx);
            $lines[] = '';
        }

        $lines[] = '━━━━━━━━━━━━━━━━━━';

        return implode("\n", $lines);
    }

    // ============================================================
    //  🧾 التفاصيل
    // ============================================================

    public static function details(Transaction $transaction): string
    {
        $statusIcon = self::statusIcon($transaction->status);
        $typeIcon = self::typeIcon($transaction->type);

        $amount = $transaction->getEffectiveAmount();
        $currency = $transaction->getEffectiveCurrency();
        $currencyLabel = self::currencyLabel($currency);

        $lines = [
            '🧾 <b>تفاصيل العملية</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🆔 <b>الرقم:</b> <code>#' . $transaction->id . '</code>',
            '🔖 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
            '',
            '📋 <b>النوع:</b> ' . $typeIcon . ' ' . $transaction->type_label,
            '📊 <b>الحالة:</b> ' . $statusIcon,
            '',
            '💰 <b>المبلغ:</b> <b>' . number_format((float) $amount, 2) . '</b> ' . $currencyLabel,
        ];

        if ($transaction->commission_amount > 0) {
            $lines[] = '💼 <b>العمولة:</b> <b>' . number_format((float) $transaction->commission_amount, 2) . '</b>';
        }

        $lines[] = '';
        $lines[] = '📅 <b>التاريخ:</b> ' . $transaction->created_at?->format('Y-m-d H:i');

        if ($transaction->completed_at) {
            $lines[] = '✅ <b>اكتمل:</b> ' . $transaction->completed_at->format('Y-m-d H:i');
        }

        if ($transaction->notes) {
            $lines[] = '';
            $lines[] = '📝 <b>ملاحظات:</b>';
            $lines[] = '<i>' . htmlspecialchars($transaction->notes, ENT_QUOTES, 'UTF-8') . '</i>';
        }

        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━';

        return implode("\n", $lines);
    }

    // ============================================================
    //  Helpers
    // ============================================================

    /**
     * 🎨 تسمية العملة بالعربية
     */
    private static function currencyLabel(string $code): string
    {
        return match (strtoupper($code)) {
            'NSP' => 'NSP (ليرة سورية جديدة)',
            'USD' => 'USD (دولار)',
            'NPS' => 'NPS (ليرة سورية قديمة)',
            'SYP' => 'SYP (ليرة سورية)',
            default => $code,
        };
    }

    private static function getFiltered(User $user, string $filter, int $page, int $perPage)
    {
        $query = Transaction::where('user_id', $user->id)->latestFirst();

        $query = match ($filter) {
            'deposits'    => $query->deposits(),
            'withdrawals' => $query->withdrawals(),
            'exchanges'   => $query->ofType(Transaction::TYPE_EXCHANGE),
            'pending'     => $query->pending(),
            'completed'   => $query->completed(),
            'rejected'    => $query->rejected(),
            default       => $query,
        };

        return $query->skip(($page - 1) * $perPage)->take($perPage)->get();
    }

    private static function countFiltered(User $user, string $filter): int
    {
        $query = Transaction::where('user_id', $user->id);

        return match ($filter) {
            'deposits'    => $query->deposits()->count(),
            'withdrawals' => $query->withdrawals()->count(),
            'exchanges'   => $query->ofType(Transaction::TYPE_EXCHANGE)->count(),
            'pending'     => $query->pending()->count(),
            'completed'   => $query->completed()->count(),
            'rejected'    => $query->rejected()->count(),
            default       => $query->count(),
        };
    }

    private static function filterLabel(string $filter): string
    {
        return match ($filter) {
            'deposits'    => '📥 الإيداعات',
            'withdrawals' => '📤 السحوبات',
            'exchanges'   => '💱 التحويلات',
            'pending'     => '🟡 المعلقة',
            'completed'   => '✅ المكتملة',
            'rejected'    => '🔴 المرفوضة',
            default       => '📜 كل العمليات',
        };
    }

    private static function formatTransaction(Transaction $transaction): string
    {
        $statusIcon = self::statusIcon($transaction->status);
        $typeIcon = self::typeIcon($transaction->type);

        $amount = $transaction->getEffectiveAmount();
        $currency = $transaction->getEffectiveCurrency();
        $currencyLabel = self::currencyLabel($currency);

        $date = $transaction->created_at?->format('m-d H:i');

        return $statusIcon . ' ' . $typeIcon
            . ' <b>#' . $transaction->id . '</b>'
            . ' | <b>' . number_format((float) $amount, 0) . '</b> ' . $currencyLabel
            . "\n" . '   📅 ' . $date;
    }

    public static function statusIcon(string $status): string
    {
        return match ($status) {
            Transaction::STATUS_PENDING   => '🟡',
            Transaction::STATUS_APPROVED  => '🟢',
            Transaction::STATUS_COMPLETED => '✅',
            Transaction::STATUS_REJECTED  => '🔴',
            Transaction::STATUS_CANCELLED => '⚫',
            Transaction::STATUS_FAILED    => '❌',
            default                       => '❔',
        };
    }

    public static function typeIcon(string $type): string
    {
        return match ($type) {
            Transaction::TYPE_DEPOSIT,
            Transaction::TYPE_DEPOSIT_USD      => '📥',
            Transaction::TYPE_WITHDRAW,
            Transaction::TYPE_WITHDRAW_USD     => '📤',
            Transaction::TYPE_EXCHANGE         => '💱',
            Transaction::TYPE_ICHANCY_DEPOSIT  => '🎯',
            Transaction::TYPE_ICHANCY_WITHDRAW => '🎮',
            Transaction::TYPE_COMMISSION       => '💼',
            Transaction::TYPE_REFUND           => '🔄',
            Transaction::TYPE_ADMIN_CREDIT     => '➕',
            Transaction::TYPE_ADMIN_DEBIT      => '➖',
            default                            => '❔',
        };
    }
}
