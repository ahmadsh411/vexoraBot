<?php

namespace App\Services;

use App\Jobs\BroadcastMessageJob;
use App\Models\User;

class BroadcastService
{
    /**
     * إرسال بث جماعي (Job).
     */
    public function broadcast(string $target, string $text, int $adminId): int
    {
        $count = $this->countTarget($target);

        if ($count === 0) {
            return 0;
        }

        BroadcastMessageJob::dispatch($target, $text, $adminId);

        return $count;
    }

    /**
     * عدد المستهدفين.
     */
    public function countTarget(string $target): int
    {
        return match ($target) {
            'all' => User::whereNotNull('telegram_id')->count(),
            'balance_nsp' => User::whereNotNull('telegram_id')
                ->whereHas('wallet', fn($q) => $q->where('balance_nsp', '>', 0))
                ->count(),
            'balance_usd' => User::whereNotNull('telegram_id')
                ->whereHas('wallet', fn($q) => $q->where('balance_usd', '>', 0))
                ->count(),
            'referrers' => User::whereNotNull('telegram_id')
                ->where('referrals_count', '>', 0)
                ->count(),
            'new' => User::whereNotNull('telegram_id')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'inactive' => User::whereNotNull('telegram_id')
                ->where(function ($q) {
                    $q->whereNull('last_login_at')
                        ->orWhere('last_login_at', '<', now()->subDays(30));
                })
                ->count(),
            'has_account' => User::whereNotNull('telegram_id')
                ->has('paymentAccounts')
                ->count(),
            default => User::where('is_admin', false)
                ->where('is_super_admin', false)
                ->whereNotNull('telegram_id')
                ->where('is_active', true)
                ->count(),
        };
    }

    /**
     * تسمية الفئة.
     */
    public function targetLabel(string $target): string
    {
        return match ($target) {
            'all'         => '👥 الكل',
            'balance_nsp' => '💰 لديهم رصيد SYP',
            'balance_usd' => '💵 لديهم رصيد USD',
            'referrers'   => '🎯 لديهم إحالات',
            'new'         => '🆕 الجدد (7 أيام)',
            'inactive'    => '😴 الخاملون (30 يوم)',
            'has_account' => '🏦 لديهم حساب دفع',
            default       => '❓ غير معروف',
        };
    }
}
