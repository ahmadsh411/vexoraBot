<?php

namespace App\Telegram\Screens\Admin\Analytics;

use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

class AnalyticsScreen
{
    /**
     * الصفحة الرئيسية للتحليلات.
     */
    public static function text(): string
    {
        // ============================================================
        //  المستخدمون
        // ============================================================
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $blockedUsers = User::where('is_active', false)->count();
        $newUsersToday = User::whereDate('created_at', today())->count();

        // ============================================================
        //  المالية
        // ============================================================
        $totalDeposits = (float) Transaction::completed()
            ->deposits()
            ->sum('amount_to');

        $totalWithdrawals = (float) Transaction::completed()
            ->withdrawals()
            ->sum('amount_from');

        $totalCommissions = (float) Transaction::completed()
            ->sum('commission_amount');

        // ============================================================
        //  الإحالات
        // ============================================================
        $totalReferrals = Referral::count();
        $totalReferralRewards = (float) ReferralReward::paid()->sum('amount');
        $totalReferrers = User::where('referrals_count', '>', 0)->count();

        // ============================================================
        //  البناء
        // ============================================================
        return implode("\n", [
            '📊 <b>التحليلات</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👥 <b>المستخدمون:</b>',
            '├── الإجمالي: <b>' . number_format($totalUsers) . '</b>',
            '├── 🟢 النشطون: <b>' . number_format($activeUsers) . '</b>',
            '├── 🔴 الموقوفون: <b>' . number_format($blockedUsers) . '</b>',
            '└── 🆕 اليوم: <b>' . number_format($newUsersToday) . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💰 <b>المالية:</b>',
            '├── 📥 الإيداعات: <b>' . number_format($totalDeposits, 0) . '</b> NSP (ليرة سورية جديدة)',
            '├── 📤 السحوبات: <b>' . number_format($totalWithdrawals, 0) . '</b> NSP (ليرة سورية جديدة)',
            '├── 💼 العمولات: <b>' . number_format($totalCommissions, 0) . '</b> NSP (ليرة سورية جديدة)',
            '└── 📊 الصافي: <b>' . number_format($totalDeposits - $totalWithdrawals, 0) . '</b> NSP (ليرة سورية جديدة)',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎯 <b>الإحالات:</b>',
            '├── 👥 المُحيلون: <b>' . number_format($totalReferrers) . '</b>',
            '├── 🤝 الإحالات: <b>' . number_format($totalReferrals) . '</b>',
            '└── 🎁 المكافآت: <b>' . number_format($totalReferralRewards, 0) . '</b> NSP (ليرة سورية جديدة)',
            '',
            '━━━━━━━━━━━━━━━━━━',
        ]);
    }

    /**
     * تحليلات المستخدمين.
     */
    public static function usersText(): string
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $blockedUsers = User::where('is_active', false)->count();

        // اليوم
        $newToday = User::whereDate('created_at', today())->count();

        // آخر 7 أيام
        $newWeek = User::where('created_at', '>=', now()->subDays(7))->count();

        // آخر 30 يوم
        $newMonth = User::where('created_at', '>=', now()->subDays(30))->count();

        // نسبة النشاط
        $activeRate = $totalUsers > 0
            ? round(($activeUsers / $totalUsers) * 100, 1)
            : 0;

        // ============================================================
        //  البناء
        // ============================================================
        return implode("\n", [
            '👥 <b>تحليلات المستخدمين</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الإجمالي:</b>',
            '├── 👥 الكل: <b>' . number_format($totalUsers) . '</b>',
            '├── 🟢 نشط: <b>' . number_format($activeUsers) . '</b>',
            '├── 🔴 موقوف: <b>' . number_format($blockedUsers) . '</b>',
            '└── 📈 نسبة النشاط: <b>' . $activeRate . '%</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🆕 <b>المسجلون الجدد:</b>',
            '├── 📅 اليوم: <b>' . number_format($newToday) . '</b>',
            '├── 📆 آخر 7 أيام: <b>' . number_format($newWeek) . '</b>',
            '└── 🗓️ آخر 30 يوم: <b>' . number_format($newMonth) . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👑 <b>الأدمن:</b> <b>' . User::where('is_admin', true)->count() . '</b>',
            '🗑️ <b>المحذوفون:</b> <b>' . User::onlyTrashed()->count() . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
        ]);
    }

    /**
     * تحليلات المالية.
     */
    public static function financeText(): string
    {
        // اليوم
        $todayDeposits = (float) Transaction::completed()
            ->deposits()
            ->whereDate('completed_at', today())
            ->sum('amount_to');

        $todayWithdrawals = (float) Transaction::completed()
            ->withdrawals()
            ->whereDate('completed_at', today())
            ->sum('amount_from');

        // آخر 7 أيام
        $weekDeposits = (float) Transaction::completed()
            ->deposits()
            ->where('completed_at', '>=', now()->subDays(7))
            ->sum('amount_to');

        $weekWithdrawals = (float) Transaction::completed()
            ->withdrawals()
            ->where('completed_at', '>=', now()->subDays(7))
            ->sum('amount_from');

        // آخر 30 يوم
        $monthDeposits = (float) Transaction::completed()
            ->deposits()
            ->where('completed_at', '>=', now()->subDays(30))
            ->sum('amount_to');

        $monthWithdrawals = (float) Transaction::completed()
            ->withdrawals()
            ->where('completed_at', '>=', now()->subDays(30))
            ->sum('amount_from');

        // العمولات
        $totalCommissions = (float) Transaction::completed()
            ->sum('commission_amount');

        // عدد العمليات
        $totalTransactions = Transaction::completed()->count();
        $pendingTransactions = Transaction::pending()->count();

        // ============================================================
        //  البناء
        // ============================================================
        return implode("\n", [
            '💰 <b>تحليلات المالية</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📅 <b>اليوم:</b>',
            '├── 📥 إيداع: <b>' . number_format($todayDeposits, 0) . '</b> NSP (ليرة سورية جديدة)',
            '└── 📤 سحب: <b>' . number_format($todayWithdrawals, 0) . '</b> NSP (ليرة سورية جديدة)',
            '',
            '📆 <b>آخر 7 أيام:</b>',
            '├── 📥 إيداع: <b>' . number_format($weekDeposits, 0) . '</b> NSP (ليرة سورية جديدة)',
            '└── 📤 سحب: <b>' . number_format($weekWithdrawals, 0) . '</b> NSP (ليرة سورية جديدة)',
            '',
            '🗓️ <b>آخر 30 يوم:</b>',
            '├── 📥 إيداع: <b>' . number_format($monthDeposits, 0) . '</b> NSP (ليرة سورية جديدة)',
            '└── 📤 سحب: <b>' . number_format($monthWithdrawals, 0) . '</b> NSP (ليرة سورية جديدة)',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💼 <b>العمولات (كل الفترات):</b>',
            '<b>' . number_format($totalCommissions, 0) . '</b> NSP (ليرة سورية جديدة)',
            '',
            '📊 <b>العمليات:</b>',
            '├── ✅ مكتملة: <b>' . number_format($totalTransactions) . '</b>',
            '└── 🟡 معلقة: <b>' . number_format($pendingTransactions) . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
        ]);
    }

    /**
     * تحليلات الإحالات.
     */
    public static function referralsText(): string
    {
        $totalReferrals = Referral::count();
        $l1Count = Referral::level1()->count();
        $l2Count = Referral::level2()->count();

        $activeReferrers = User::where('referrals_count', '>', 0)->count();

        // المكافآت
        $totalRewards = (float) ReferralReward::paid()->sum('amount');
        $instantRewards = (float) ReferralReward::paid()->instant()->sum('amount');
        $cycleRewards = (float) ReferralReward::paid()->cycle()->sum('amount');

        // آخر 30 يوم
        $monthRewards = (float) ReferralReward::paid()
            ->where('paid_at', '>=', now()->subDays(30))
            ->sum('amount');

        // متوسط المكافأة
        $avgReward = $totalReferrals > 0
            ? round($totalRewards / $totalReferrals, 2)
            : 0;

        // ============================================================
        //  البناء
        // ============================================================
        return implode("\n", [
            '🎯 <b>تحليلات الإحالات</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👥 <b>الإحالات:</b>',
            '├── 📊 الإجمالي: <b>' . number_format($totalReferrals) . '</b>',
            '├── 🥇 L1: <b>' . number_format($l1Count) . '</b>',
            '└── 🥈 L2: <b>' . number_format($l2Count) . '</b>',
            '',
            '👤 <b>المُحيلون:</b> <b>' . number_format($activeReferrers) . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💰 <b>المكافآت (كل الفترات):</b>',
            '├── 📊 الإجمالي: <b>' . number_format($totalRewards, 0) . '</b> NSP (ليرة سورية جديدة)',
            '├── ⚡ الفورية: <b>' . number_format($instantRewards, 0) . '</b> NSP (ليرة سورية جديدة)',
            '└── 📅 الدورية: <b>' . number_format($cycleRewards, 0) . '</b> NSP (ليرة سورية جديدة)',
            '',
            '📆 <b>آخر 30 يوم:</b>',
            '<b>' . number_format($monthRewards, 0) . '</b> NSP (ليرة سورية جديدة)',
            '',
            '📈 <b>متوسط المكافأة/إحالة:</b>',
            '<b>' . number_format($avgReward, 2) . '</b> NSP (ليرة سورية جديدة)',
            '',
            '━━━━━━━━━━━━━━━━━━',
        ]);
    }

    /**
     * نص المقارنة.
     */
    public static function comparisonText(string $period = 'day'): string
    {
        switch ($period) {
            case 'week':
                $title = '📆 أسبوع vs أسبوع';
                $currentStart = now()->startOfWeek();
                $currentEnd = now();
                $prevStart = now()->subWeek()->startOfWeek();
                $prevEnd = now()->subWeek()->endOfWeek();
                break;

            case 'month':
                $title = '🗓️ شهر vs شهر';
                $currentStart = now()->startOfMonth();
                $currentEnd = now();
                $prevStart = now()->subMonth()->startOfMonth();
                $prevEnd = now()->subMonth()->endOfMonth();
                break;

            default:
                $title = '📅 اليوم vs الأمس';
                $currentStart = now()->startOfDay();
                $currentEnd = now();
                $prevStart = now()->subDay()->startOfDay();
                $prevEnd = now()->subDay()->endOfDay();
                break;
        }

        // ============================================================
        //  المستخدمون
        // ============================================================
        $currentUsers = User::whereBetween('created_at', [$currentStart, $currentEnd])->count();
        $prevUsers = User::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        // ============================================================
        //  الإيداعات
        // ============================================================
        $currentDeposits = (float) Transaction::completed()
            ->deposits()
            ->whereBetween('completed_at', [$currentStart, $currentEnd])
            ->sum('amount_to');

        $prevDeposits = (float) Transaction::completed()
            ->deposits()
            ->whereBetween('completed_at', [$prevStart, $prevEnd])
            ->sum('amount_to');

        // ============================================================
        //  السحوبات
        // ============================================================
        $currentWithdrawals = (float) Transaction::completed()
            ->withdrawals()
            ->whereBetween('completed_at', [$currentStart, $currentEnd])
            ->sum('amount_from');

        $prevWithdrawals = (float) Transaction::completed()
            ->withdrawals()
            ->whereBetween('completed_at', [$prevStart, $prevEnd])
            ->sum('amount_from');

        // ============================================================
        //  الإحالات
        // ============================================================
        $currentReferrals = Referral::whereBetween('created_at', [$currentStart, $currentEnd])->count();
        $prevReferrals = Referral::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        // ============================================================
        //  البناء
        // ============================================================
        return implode("\n", [
            $title,
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👥 <b>المستخدمون:</b>',
            '├── الحالي: <b>' . number_format($currentUsers) . '</b>',
            '├── السابق: <b>' . number_format($prevUsers) . '</b>',
            '└── ' . self::trend($currentUsers, $prevUsers),
            '',
            '📥 <b>الإيداعات:</b>',
            '├── الحالي: <b>' . number_format($currentDeposits, 0) . '</b>',
            '├── السابق: <b>' . number_format($prevDeposits, 0) . '</b>',
            '└── ' . self::trend($currentDeposits, $prevDeposits),
            '',
            '📤 <b>السحوبات:</b>',
            '├── الحالي: <b>' . number_format($currentWithdrawals, 0) . '</b>',
            '├── السابق: <b>' . number_format($prevWithdrawals, 0) . '</b>',
            '└── ' . self::trend($currentWithdrawals, $prevWithdrawals),
            '',
            '🎯 <b>الإحالات:</b>',
            '├── الحالي: <b>' . number_format($currentReferrals) . '</b>',
            '├── السابق: <b>' . number_format($prevReferrals) . '</b>',
            '└── ' . self::trend($currentReferrals, $prevReferrals),
            '',
            '━━━━━━━━━━━━━━━━━━',
        ]);
    }

    /**
     * حساب الاتجاه (صعود/هبوط).
     */
    private static function trend(float $current, float $previous): string
    {
        if ($previous == 0) {
            return $current > 0 ? '📈 جديد' : '➡️ لا تغيير';
        }

        $change = (($current - $previous) / $previous) * 100;

        if ($change > 0) {
            return '📈 <b>+' . round($change, 1) . '%</b>';
        }

        if ($change < 0) {
            return '📉 <b>' . round($change, 1) . '%</b>';
        }

        return '➡️ <b>لا تغيير</b>';
    }
}
