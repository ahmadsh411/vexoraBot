<?php

namespace App\Telegram\Screens\Admin\Finance;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Telegram\Keyboards\AdminsKeyboard\Finance\MainWalletKeyboard;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class MainWalletScreen
{
    // ============================================================
    //  💾 الشاشة الرئيسية
    // ============================================================
    public static function text(): string
    {
        $wallet = Wallet::where('type', Wallet::TYPE_MAIN)->first();

        if (! $wallet) {
            return implode("\n", [
                '🏦 <b>المحفظة الرئيسية</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '❌ <b>المحفظة غير موجودة</b>',
                '',
                'شغّل: <code>php artisan wallet:init</code>',
            ]);
        }

        $stats = self::getStats();

        return implode("\n", [
            '🏦 <b>المحفظة الرئيسية</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💰 <b>الرصيد الحالي:</b>',
            '   💰 <b>' . number_format((float) $wallet->balance_nsp, 2) . '</b> NSP',
            '   💵 <b>' . number_format((float) $wallet->balance_usd, 2) . '</b> USD',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '📊 <b>الإجماليات (منذ البداية):</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📥 <b>الإيداعات:</b>',
            '   💰 NSP: <b>' . number_format($stats['total_deposits_nsp'], 2) . '</b>  (' . $stats['count_deposits_nsp'] . ')',
            '   💵 USD: <b>' . number_format($stats['total_deposits_usd'], 2) . '</b>  (' . $stats['count_deposits_usd'] . ')',
            '',
            '📤 <b>السحوبات:</b>',
            '   💰 NSP: <b>' . number_format($stats['total_withdrawals_nsp'], 2) . '</b>  (' . $stats['count_withdrawals_nsp'] . ')',
            '   💵 USD: <b>' . number_format($stats['total_withdrawals_usd'], 2) . '</b>  (' . $stats['count_withdrawals_usd'] . ')',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '📅 <b>آخر 7 أيام:</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📥 <b>الإيداعات:</b>',
            '   💰 NSP: <b>' . number_format($stats['week_deposits_nsp'], 2) . '</b>',
            '   💵 USD: <b>' . number_format($stats['week_deposits_usd'], 2) . '</b>',
            '',
            '📤 <b>السحوبات:</b>',
            '   💰 NSP: <b>' . number_format($stats['week_withdrawals_nsp'], 2) . '</b>',
            '   💵 USD: <b>' . number_format($stats['week_withdrawals_usd'], 2) . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🕐 <b>آخر تحديث:</b> ' . now()->format('Y-m-d H:i'),
        ]);
    }

    // ============================================================
    //  📊 الإحصائيات المفصّلة
    // ============================================================
    public static function statsText(): string
    {
        $wallet = Wallet::where('type', Wallet::TYPE_MAIN)->first();

        // ─── اليوم ───
        $today = self::getPeriodStats(today()->startOfDay(), now());

        // ─── المعلقة ───
        $pendingDepositsNsp  = Transaction::pending()->deposits()->where('to_currency', 'NSP')->count();
        $pendingDepositsUsd  = Transaction::pending()->deposits()->where('to_currency', 'USD')->count();
        $pendingWithdrawNsp  = Transaction::pending()->withdrawals()->where('from_currency', 'NSP')->count();
        $pendingWithdrawUsd  = Transaction::pending()->withdrawals()->where('from_currency', 'USD')->count();

        $pendingDepositsNspSum = (float) Transaction::pending()->deposits()->where('to_currency', 'NSP')->sum('amount_to');
        $pendingDepositsUsdSum = (float) Transaction::pending()->deposits()->where('to_currency', 'USD')->sum('amount_to');
        $pendingWithdrawNspSum = (float) Transaction::pending()->withdrawals()->where('from_currency', 'NSP')->sum('amount_from');
        $pendingWithdrawUsdSum = (float) Transaction::pending()->withdrawals()->where('from_currency', 'USD')->sum('amount_from');

        return implode("\n", [
            '📊 <b>إحصائيات مفصلة</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🏦 <b>الرصيد الحالي:</b>',
            '   💰 <b>' . number_format((float) ($wallet?->balance_nsp ?? 0), 2) . '</b> NSP',
            '   💵 <b>' . number_format((float) ($wallet?->balance_usd ?? 0), 2) . '</b> USD',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '📅 <b>اليوم:</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📥 <b>الإيداعات:</b>',
            '   💰 NSP: <b>' . number_format($today['deposits_nsp'], 2) . '</b>  (' . $today['count_deposits_nsp'] . ')',
            '   💵 USD: <b>' . number_format($today['deposits_usd'], 2) . '</b>  (' . $today['count_deposits_usd'] . ')',
            '',
            '📤 <b>السحوبات:</b>',
            '   💰 NSP: <b>' . number_format($today['withdrawals_nsp'], 2) . '</b>  (' . $today['count_withdrawals_nsp'] . ')',
            '   💵 USD: <b>' . number_format($today['withdrawals_usd'], 2) . '</b>  (' . $today['count_withdrawals_usd'] . ')',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '🟡 <b>المعلقة:</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📥 <b>إيداعات معلقة:</b>',
            '   💰 NSP: <b>' . number_format($pendingDepositsNspSum, 2) . '</b>  (' . $pendingDepositsNsp . ')',
            '   💵 USD: <b>' . number_format($pendingDepositsUsdSum, 2) . '</b>  (' . $pendingDepositsUsd . ')',
            '',
            '📤 <b>سحوبات معلقة:</b>',
            '   💰 NSP: <b>' . number_format($pendingWithdrawNspSum, 2) . '</b>  (' . $pendingWithdrawNsp . ')',
            '   💵 USD: <b>' . number_format($pendingWithdrawUsdSum, 2) . '</b>  (' . $pendingWithdrawUsd . ')',
            '',
            '━━━━━━━━━━━━━━━━━━',
        ]);
    }

    // ============================================================
    //  🎹 الكيبورد
    // ============================================================
    public static function keyboard(): InlineKeyboardMarkup
    {
        return MainWalletKeyboard::make();
    }

    // ============================================================
    //  📊 جمع الإحصائيات
    // ============================================================
    private static function getStats(): array
    {
        $week = now()->subDays(7);

        return [
            // ─── الإجماليات — NSP ───
            'total_deposits_nsp' => (float) Transaction::completed()
                ->deposits()
                ->where('to_currency', 'NSP')
                ->sum('amount_to'),

            'count_deposits_nsp' => Transaction::completed()
                ->deposits()
                ->where('to_currency', 'NSP')
                ->count(),

            'total_withdrawals_nsp' => (float) Transaction::completed()
                ->withdrawals()
                ->where('from_currency', 'NSP')
                ->sum('amount_from'),

            'count_withdrawals_nsp' => Transaction::completed()
                ->withdrawals()
                ->where('from_currency', 'NSP')
                ->count(),

            // ─── الإجماليات — USD ───
            'total_deposits_usd' => (float) Transaction::completed()
                ->deposits()
                ->where('to_currency', 'USD')
                ->sum('amount_to'),

            'count_deposits_usd' => Transaction::completed()
                ->deposits()
                ->where('to_currency', 'USD')
                ->count(),

            'total_withdrawals_usd' => (float) Transaction::completed()
                ->withdrawals()
                ->where('from_currency', 'USD')
                ->sum('amount_from'),

            'count_withdrawals_usd' => Transaction::completed()
                ->withdrawals()
                ->where('from_currency', 'USD')
                ->count(),

            // ─── آخر 7 أيام — NSP ───
            'week_deposits_nsp' => (float) Transaction::completed()
                ->deposits()
                ->where('to_currency', 'NSP')
                ->where('completed_at', '>=', $week)
                ->sum('amount_to'),

            'week_withdrawals_nsp' => (float) Transaction::completed()
                ->withdrawals()
                ->where('from_currency', 'NSP')
                ->where('completed_at', '>=', $week)
                ->sum('amount_from'),

            // ─── آخر 7 أيام — USD ───
            'week_deposits_usd' => (float) Transaction::completed()
                ->deposits()
                ->where('to_currency', 'USD')
                ->where('completed_at', '>=', $week)
                ->sum('amount_to'),

            'week_withdrawals_usd' => (float) Transaction::completed()
                ->withdrawals()
                ->where('from_currency', 'USD')
                ->where('completed_at', '>=', $week)
                ->sum('amount_from'),
        ];
    }

    // ============================================================
    //  📊 إحصائيات فترة محددة
    // ============================================================
    private static function getPeriodStats($from, $to): array
    {
        return [
            // ─── الإيداعات ───
            'deposits_nsp' => (float) Transaction::completed()
                ->deposits()
                ->where('to_currency', 'NSP')
                ->whereBetween('completed_at', [$from, $to])
                ->sum('amount_to'),

            'deposits_usd' => (float) Transaction::completed()
                ->deposits()
                ->where('to_currency', 'USD')
                ->whereBetween('completed_at', [$from, $to])
                ->sum('amount_to'),

            'count_deposits_nsp' => Transaction::completed()
                ->deposits()
                ->where('to_currency', 'NSP')
                ->whereBetween('completed_at', [$from, $to])
                ->count(),

            'count_deposits_usd' => Transaction::completed()
                ->deposits()
                ->where('to_currency', 'USD')
                ->whereBetween('completed_at', [$from, $to])
                ->count(),

            // ─── السحوبات ───
            'withdrawals_nsp' => (float) Transaction::completed()
                ->withdrawals()
                ->where('from_currency', 'NSP')
                ->whereBetween('completed_at', [$from, $to])
                ->sum('amount_from'),

            'withdrawals_usd' => (float) Transaction::completed()
                ->withdrawals()
                ->where('from_currency', 'USD')
                ->whereBetween('completed_at', [$from, $to])
                ->sum('amount_from'),

            'count_withdrawals_nsp' => Transaction::completed()
                ->withdrawals()
                ->where('from_currency', 'NSP')
                ->whereBetween('completed_at', [$from, $to])
                ->count(),

            'count_withdrawals_usd' => Transaction::completed()
                ->withdrawals()
                ->where('from_currency', 'USD')
                ->whereBetween('completed_at', [$from, $to])
                ->count(),
        ];
    }
}
