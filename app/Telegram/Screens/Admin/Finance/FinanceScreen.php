<?php

namespace App\Telegram\Screens\Admin\Finance;

use App\Models\Transaction;
use App\Models\Wallet;

class FinanceScreen
{
    public static function text(): string
    {
        $mainWallet = Wallet::where('type', Wallet::TYPE_MAIN)->first();

        $balanceSyp = $mainWallet?->balance_nsp ?? 0;
        $balanceUsd = $mainWallet?->balance_usd ?? 0;

        $pendingDeposits  = Transaction::pending()->deposits()->count();
        $pendingWithdraws = Transaction::pending()->withdrawals()->count();

        $todayDeposits = (float) Transaction::completed()
            ->deposits()
            ->whereDate('completed_at', today())
            ->sum('amount_to');

        $todayWithdrawals = (float) Transaction::completed()
            ->withdrawals()
            ->whereDate('completed_at', today())
            ->sum('amount_from');

        return implode("\n", [
            '💰 <b>الإدارة المالية</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🏦 <b>المحفظة الرئيسية:</b>',
            '   💰 <b>' . number_format((float) $balanceSyp, 2) . '</b> NSP (ليرة سورية جديدة)',
            '   💵 <b>' . number_format((float) $balanceUsd, 2) . '</b> USD (دولار)',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>اليوم:</b>',
            '   📥 إيداع: <b>' . number_format($todayDeposits, 2) . '</b>',
            '   📤 سحب: <b>' . number_format($todayWithdrawals, 2) . '</b>',
            '',
            '🟡 <b>طلبات معلقة:</b>',
            '   📥 إيداع: <b>' . $pendingDeposits . '</b>',
            '   📤 سحب: <b>' . $pendingWithdraws . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
        ]);
    }
}
