<?php

namespace App\Telegram\Screens\Admin\Finance;

use App\Models\Transaction;
use App\Telegram\Keyboards\AdminsKeyboard\Finance\ReportsKeyboard;
use Carbon\Carbon;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ReportsScreen
{
    public static function text(): string
    {
        return implode("\n", [
            '📊 <b>التقارير المالية</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            'اختر الفترة الزمنية:',
            '',
            '📅 <b>اليوم</b> — من منتصف الليل',
            '📆 <b>آخر 7 أيام</b>',
            '🗓️ <b>آخر 30 يوماً</b>',
            '📅 <b>هذا الشهر</b>',
            '📅 <b>الشهر الماضي</b>',
            '📆 <b>هذه السنة</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
        ]);
    }

    public static function keyboard(): InlineKeyboardMarkup
    {
        return ReportsKeyboard::make();
    }

    public static function reportText(
        string $title,
        Carbon $from,
        Carbon $to,
    ): string {
        $stats = self::getStats($from, $to);
        $previousStats = self::getStats(
            (clone $from)->subDays($to->diffInDays($from) + 1),
            (clone $from)->subDay(),
        );

        return implode("\n", [
            '📊 <b>' . $title . '</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📅 <b>الفترة:</b>',
            '   من: ' . $from->format('Y-m-d'),
            '   إلى: ' . $to->format('Y-m-d'),
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📥 <b>الإيداعات:</b>',
            '   العدد: <b>' . $stats['deposits_count'] . '</b>',
            '   💰 NSP (ليرة سورية جديدة): <b>' . number_format($stats['deposits_syp'], 2) . '</b>',
            '   💵 USD (دولار): <b>' . number_format($stats['deposits_usd'], 2) . '</b>',
            self::comparisonText('deposits_count', $stats, $previousStats),
            '',
            '📤 <b>السحوبات:</b>',
            '   العدد: <b>' . $stats['withdrawals_count'] . '</b>',
            '   💰 NSP (ليرة سورية جديدة): <b>' . number_format($stats['withdrawals_syp'], 2) . '</b>',
            '   💵 USD (دولار): <b>' . number_format($stats['withdrawals_usd'], 2) . '</b>',
            self::comparisonText('withdrawals_count', $stats, $previousStats),
            '',
            '💱 <b>التحويلات:</b> <b>' . $stats['exchanges_count'] . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الصافي (الإيداع - السحب):</b>',
            '   💰 <b>' . number_format($stats['net_syp'], 2) . '</b> NSP (ليرة سورية جديدة)',
            '   💵 <b>' . number_format($stats['net_usd'], 2) . '</b> USD (دولار)',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🔧 <b>التعديلات الإدارية:</b>',
            '   ➕ إضافات:',
            '      💰 <b>' . number_format($stats['admin_credits_syp'], 2) . '</b> NSP (ليرة سورية جديدة)',
            '      💵 <b>' . number_format($stats['admin_credits_usd'], 2) . '</b> USD (دولار)',
            '   ➖ خصومات:',
            '      💰 <b>' . number_format($stats['admin_debits_syp'], 2) . '</b> NSP (ليرة سورية جديدة)',
            '      💵 <b>' . number_format($stats['admin_debits_usd'], 2) . '</b> USD (دولار)',
            '',
            '📊 <b>صافي التعديلات:</b>',
            '   💰 <b>' . number_format($stats['admin_net_syp'], 2) . '</b> NSP (ليرة سورية جديدة)',
            '   💵 <b>' . number_format($stats['admin_net_usd'], 2) . '</b> USD (دولار)',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📈 <b>الصافي الحقيقي:</b>',
            '   💰 <b>' . number_format($stats['net_real_syp'], 2) . '</b> NSP (ليرة سورية جديدة)',
            '   💵 <b>' . number_format($stats['net_real_usd'], 2) . '</b> USD (دولار)',
            '',
            '🟡 <b>معلقة:</b> <b>' . $stats['pending_count'] . '</b>',
            '✅ <b>مكتملة:</b> <b>' . $stats['completed_count'] . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🏆 <b>أكثر المستخدمين نشاطاً:</b>',
            ...self::topUsersText($from, $to),
            '',
            '━━━━━━━━━━━━━━━━━━',
        ]);
    }

    public static function overviewText(): string
    {
        $allTime = self::getStats(
            Carbon::create(2000, 1, 1),
            now(),
        );

        $today = self::getStats(now()->startOfDay(), now());
        $week = self::getStats(now()->subDays(7), now());
        $month = self::getStats(now()->subDays(30), now());

        return implode("\n", [
            '📊 <b>تقرير شامل</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📅 <b>اليوم:</b>',
            '   📥 ' . $today['deposits_count'] . ' (' . number_format($today['deposits_syp'], 0) . ' NSP (ليرة سورية جديدة))',
            '   📤 ' . $today['withdrawals_count'] . ' (' . number_format($today['withdrawals_syp'], 0) . ' NSP (ليرة سورية جديدة))',
            '   🔧 صافي التعديلات: ' . number_format($today['admin_net_syp'], 0) . ' NSP (ليرة سورية جديدة)',
            '',
            '📆 <b>آخر 7 أيام:</b>',
            '   📥 ' . $week['deposits_count'] . ' (' . number_format($week['deposits_syp'], 0) . ' NSP (ليرة سورية جديدة))',
            '   📤 ' . $week['withdrawals_count'] . ' (' . number_format($week['withdrawals_syp'], 0) . ' NSP (ليرة سورية جديدة))',
            '   🔧 صافي التعديلات: ' . number_format($week['admin_net_syp'], 0) . ' NSP (ليرة سورية جديدة)',
            '',
            '🗓️ <b>آخر 30 يوماً:</b>',
            '   📥 ' . $month['deposits_count'] . ' (' . number_format($month['deposits_syp'], 0) . ' NSP (ليرة سورية جديدة))',
            '   📤 ' . $month['withdrawals_count'] . ' (' . number_format($month['withdrawals_syp'], 0) . ' NSP (ليرة سورية جديدة))',
            '   🔧 صافي التعديلات: ' . number_format($month['admin_net_syp'], 0) . ' NSP (ليرة سورية جديدة)',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📈 <b>الإجمالي (منذ البداية):</b>',
            '   📥 إيداعات: <b>' . $allTime['deposits_count'] . '</b>',
            '      💰 ' . number_format($allTime['deposits_syp'], 2) . ' NSP (ليرة سورية جديدة)',
            '      💵 ' . number_format($allTime['deposits_usd'], 2) . ' USD (دولار)',
            '',
            '   📤 سحوبات: <b>' . $allTime['withdrawals_count'] . '</b>',
            '      💰 ' . number_format($allTime['withdrawals_syp'], 2) . ' NSP (ليرة سورية جديدة)',
            '      💵 ' . number_format($allTime['withdrawals_usd'], 2) . ' USD (دولار)',
            '',
            '   🔧 تعديلات إدارية:',
            '      ➕ NSP: ' . number_format($allTime['admin_credits_syp'], 2),
            '      ➖ NSP: ' . number_format($allTime['admin_debits_syp'], 2),
            '',
            '   📊 الصافي الحقيقي: <b>' . number_format($allTime['net_real_syp'], 2) . '</b> NSP (ليرة سورية جديدة)',
            '',
            '━━━━━━━━━━━━━━━━━━',
        ]);
    }

    public static function reportKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔄 تحديث',
                    callback_data: 'admin.finance.reports',
                ),
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: 'admin.finance.reports',
                ),
            );
    }

    private static function getStats(Carbon $from, Carbon $to): array
    {
        $completed = Transaction::completed()
            ->whereBetween('completed_at', [$from, $to]);

        $deposits = (clone $completed)->deposits();
        $withdrawals = (clone $completed)->withdrawals();

        $depositsCount = (clone $deposits)->count();
        $withdrawalsCount = (clone $withdrawals)->count();

        $depositsSyp = (float) (clone $deposits)
            ->where('to_currency', 'NSP')
            ->sum('amount_to');

        $depositsUsd = (float) (clone $deposits)
            ->where('to_currency', 'USD')
            ->sum('amount_to');

        $withdrawalsSyp = (float) (clone $withdrawals)
            ->where('from_currency', 'NSP')
            ->sum('amount_from');

        $withdrawalsUsd = (float) (clone $withdrawals)
            ->where('from_currency', 'USD')
            ->sum('amount_from');

        $exchangesCount = (clone $completed)
            ->where('type', Transaction::TYPE_EXCHANGE)
            ->count();

        $adminCreditsSyp = (float) (clone $completed)
            ->where('type', Transaction::TYPE_ADMIN_CREDIT)
            ->where('to_currency', 'NSP')
            ->sum('amount_to');

        $adminCreditsUsd = (float) (clone $completed)
            ->where('type', Transaction::TYPE_ADMIN_CREDIT)
            ->where('to_currency', 'USD')
            ->sum('amount_to');

        $adminDebitsSyp = (float) (clone $completed)
            ->where('type', Transaction::TYPE_ADMIN_DEBIT)
            ->where('from_currency', 'NSP')
            ->sum('amount_from');

        $adminDebitsUsd = (float) (clone $completed)
            ->where('type', Transaction::TYPE_ADMIN_DEBIT)
            ->where('from_currency', 'USD')
            ->sum('amount_from');

        $adminNetSyp = $adminCreditsSyp - $adminDebitsSyp;
        $adminNetUsd = $adminCreditsUsd - $adminDebitsUsd;

        $netSyp = $depositsSyp - $withdrawalsSyp;
        $netUsd = $depositsUsd - $withdrawalsUsd;

        $netRealSyp = $netSyp + $adminNetSyp;
        $netRealUsd = $netUsd + $adminNetUsd;

        return [
            'deposits_count'    => $depositsCount,
            'deposits_syp'      => $depositsSyp,
            'deposits_usd'      => $depositsUsd,
            'withdrawals_count' => $withdrawalsCount,
            'withdrawals_syp'   => $withdrawalsSyp,
            'withdrawals_usd'   => $withdrawalsUsd,
            'exchanges_count'   => $exchangesCount,
            'admin_credits_syp' => $adminCreditsSyp,
            'admin_credits_usd' => $adminCreditsUsd,
            'admin_debits_syp'  => $adminDebitsSyp,
            'admin_debits_usd'  => $adminDebitsUsd,
            'admin_net_syp'     => $adminNetSyp,
            'admin_net_usd'     => $adminNetUsd,
            'net_syp'           => $netSyp,
            'net_usd'           => $netUsd,
            'net_real_syp'      => $netRealSyp,
            'net_real_usd'      => $netRealUsd,
            'pending_count'     => Transaction::pending()->count(),
            'completed_count'   => (clone $completed)->count(),
        ];
    }

    private static function comparisonText(
        string $key,
        array $current,
        array $previous,
    ): string {
        $currentValue = $current[$key] ?? 0;
        $previousValue = $previous[$key] ?? 0;

        if ($previousValue === 0) {
            return '   —';
        }

        $change = (($currentValue - $previousValue) / $previousValue) * 100;

        if ($change > 0) {
            return '   📈 +' . number_format($change, 1) . '% عن السابق';
        }

        if ($change < 0) {
            return '   📉 ' . number_format($change, 1) . '% عن السابق';
        }

        return '   ➡️ لا تغيير';
    }

    private static function topUsersText(Carbon $from, Carbon $to): array
    {
        $topUsers = Transaction::completed()
            ->whereBetween('completed_at', [$from, $to])
            ->selectRaw('user_id, COUNT(*) as total_count, SUM(amount_to + amount_from) as total_amount')
            ->groupBy('user_id')
            ->orderByDesc('total_count')
            ->limit(5)
            ->with('user:id,username')
            ->get();

        if ($topUsers->isEmpty()) {
            return ['   لا يوجد'];
        }

        $lines = [];

        foreach ($topUsers as $index => $item) {
            $medal = match ($index) {
                0 => '🥇',
                1 => '🥈',
                2 => '🥉',
                default => '  ',
            };

            $username = $item->user?->username ?? 'غير معروف';
            $lines[] = "   {$medal} {$username} — {$item->total_count} عملية";
        }

        return $lines;
    }
}
