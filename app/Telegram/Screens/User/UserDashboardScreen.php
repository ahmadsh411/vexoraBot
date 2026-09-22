<?php

namespace App\Telegram\Screens\User;

use App\Models\User;

class UserDashboardScreen
{
    public static function text(User $user): string
    {
        $wallet = $user->wallet;

        $balanceSyp = $wallet ? number_format((float) $wallet->balance_nsp, 0) : '0';
        $balanceUsd = $wallet ? number_format((float) $wallet->balance_usd, 2) : '0.00';

        return implode("\n", [
            '⚡ <b>VEXORA</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎉 <b>مرحباً بك في بوت إيشانسي</b>',
            '',
            '👋 أهلاً <b>' . htmlspecialchars($user->display_name, ENT_QUOTES, 'UTF-8') . '</b>',
            '',
            '💰 <b>رصيدك:</b>',
            '├── 💰 NSP (ليرة سورية جديدة): <b>' . $balanceSyp . '</b>',
            '└── 💵 USD (دولار): <b>' . $balanceUsd . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💡 اختر الإجراء:',
        ]);
    }
}
