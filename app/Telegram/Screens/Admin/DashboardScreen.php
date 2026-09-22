<?php

namespace App\Telegram\Screens\Admin;

use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;

class DashboardScreen
{
    public static function text(?User $admin = null): string
    {
        // ═══════════════════════════════════════════════════
        //  📊 الإحصائيات
        // ═══════════════════════════════════════════════════
        $stats = Cache::remember('admin_dashboard_stats', 60, function () {
            return [
                'usersCount'        => User::count(),
                'activeUsers'       => User::where('is_active', true)->count(),
                'todayUsers'        => User::whereDate('created_at', today())->count(),
                'transactionsCount' => Transaction::count(),
                'referralsCount'    => Referral::count(),
                'totalBalance'      => (float) Wallet::where('type', 'user')->sum('balance_nsp'),
            ];
        });

        $usersCount        = $stats['usersCount'];
        $activeUsers       = $stats['activeUsers'];
        $todayUsers        = $stats['todayUsers'];
        $transactionsCount = $stats['transactionsCount'];
        $referralsCount    = $stats['referralsCount'];
        $totalBalance      = $stats['totalBalance'];

        // ═══════════════════════════════════════════════════
        //  👤 اسم الأدمن
        // ═══════════════════════════════════════════════════
        $adminName = htmlspecialchars(
            $admin?->first_name ?? $admin?->username ?? 'Admin',
            ENT_QUOTES,
            'UTF-8'
        );

        // ═══════════════════════════════════════════════════
        //  🎨 التصميم
        // ═══════════════════════════════════════════════════
        return implode("\n", [
            '<b>⚡ VEXORA</b>  —  لوحة الإدارة',
            '',
            '👋 أهلاً <b>' . $adminName . '</b>',
            '',
            '📊 <b>النشاط الحالي</b>',
            '',
            '👥  المستخدمون   ·   <code>' . number_format($usersCount) . '</code>',
            '🟢  النشطون       ·   <code>' . number_format($activeUsers) . '</code>',
            '💰  العمليات      ·   <code>' . number_format($transactionsCount) . '</code>',
            '🎯  الإحالات      ·   <code>' . number_format($referralsCount) . '</code>',
            '💎  الخزينة       ·   <code>' . number_format($totalBalance, 2) . ' NSP (ليرة سورية جديدة)</code>',
            '',
            '<i>👇 اختر قسماً للبدء</i>',
        ]);
    }
}
