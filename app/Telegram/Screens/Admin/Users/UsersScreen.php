<?php

namespace App\Telegram\Screens\Admin\Users;

use App\Models\User;

class UsersScreen
{
    public static function text(): string
    {
        // ═══════════════════════════════════════════════════
        //  📊 الإحصائيات
        // ═══════════════════════════════════════════════════
        $totalUsers    = User::count();
        $activeUsers   = User::where('is_active', true)->count();
        $inactiveUsers = User::where('is_active', false)->count();
        $newUsers      = User::whereNull('admin_seen_at')->count();
        $todayUsers    = User::whereDate('created_at', today())->count();

        // ✅ إضافي — آخر 7 أيام
        $weekUsers = User::where('created_at', '>=', now()->subDays(7))->count();

        return implode("\n", [
            '<b>👥 إدارة المستخدمين</b>',
            '',
            '📊 <b>النظرة العامة</b>',
            '',
            '👥  الإجمالي        ·   <code>' . number_format($totalUsers) . '</code>',
            '🟢  النشطون         ·   <code>' . number_format($activeUsers) . '</code>',
            '🔴  الموقوفون       ·   <code>' . number_format($inactiveUsers) . '</code>',
            '',
            '✨  الجدد (غير مقروء) ·   <code>' . number_format($newUsers) . '</code>',
            '📅  اليوم           ·   <code>' . number_format($todayUsers) . '</code>',
            '📈  هذا الأسبوع      ·   <code>' . number_format($weekUsers) . '</code>',
            '',
            '<i>👇 اختر إجراءً للبدء</i>',
        ]);
    }
}
