<?php

namespace App\Console\Commands;

use App\Models\GiftRedemption;
use App\Models\ReferralReward;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WheelSpin;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class DailyFullReport extends Command
{
    protected $signature = 'reports:full
                            {--date= : التاريخ (افتراضي: أمس)}
                            {--test : إرسال تجريبي للقناة فقط}';

    protected $description = 'إرسال التقرير اليومي الشامل';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $this->info("📊 إنشاء التقرير الشامل ليوم: {$date->format('Y-m-d')}");

        $stats = $this->gatherStats($date);

        $this->displayStats($stats);
        $this->sendToChannel($stats);

        if (! $this->option('test')) {
            $this->sendToAdmins($stats);
        }

        Log::info('Daily full report sent', [
            'date' => $date->format('Y-m-d'),
        ]);

        $this->newLine();
        $this->info('✅ تم إرسال التقرير.');

        return self::SUCCESS;
    }

    // ============================================================
    //  جمع كل الإحصائيات
    // ============================================================
    private function gatherStats(Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end   = $date->copy()->endOfDay();

        // ═══════════════════════════════════════════════════════
        //  المعاملات المكتملة
        // ═══════════════════════════════════════════════════════
        $completed = Transaction::completed()
            ->whereBetween('completed_at', [$start, $end]);

        $deposits    = (clone $completed)->deposits();
        $withdrawals = (clone $completed)->withdrawals();

        // ═══════════════════════════════════════════════════════
        //  المكافآت
        // ═══════════════════════════════════════════════════════
        $bonuses = (clone $completed)->where('type', Transaction::TYPE_DEPOSIT_BONUS);

        // ═══════════════════════════════════════════════════════
        //  iChancy
        // ═══════════════════════════════════════════════════════
        $ichancyDeposits  = (clone $completed)->where('type', 'ichancy_deposit');
        $ichancyWithdraws = (clone $completed)->where('type', 'ichancy_withdraw');

        // ═══════════════════════════════════════════════════════
        //  التحويلات (Exchange)
        // ═══════════════════════════════════════════════════════
        $exchanges = (clone $completed)->where('type', Transaction::TYPE_EXCHANGE);
        $exchangesCount = (clone $exchanges)->count();

        // ═══════════════════════════════════════════════════════
        //  عمليات الأدمن
        // ═══════════════════════════════════════════════════════
        $adminCredits = (float) (clone $completed)->where('type', Transaction::TYPE_ADMIN_CREDIT)->sum('amount_to');
        $adminDebits  = (float) (clone $completed)->where('type', Transaction::TYPE_ADMIN_DEBIT)->sum('amount_from');

        // ═══════════════════════════════════════════════════════
        //  العجلة
        // ═══════════════════════════════════════════════════════
        $wheelSpins = WheelSpin::whereBetween('created_at', [$start, $end]);
        $wheelSpinsCount = (clone $wheelSpins)->count();
        $wheelWonNsp = (float) (clone $wheelSpins)->where('currency', 'NSP')->sum('won_value');
        $wheelWonUsd = (float) (clone $wheelSpins)->where('currency', 'USD')->sum('won_value');

        // ═══════════════════════════════════════════════════════
        //  أكواد الهدايا
        // ═══════════════════════════════════════════════════════
        $giftRedemptions = GiftRedemption::whereBetween('redeemed_at', [$start, $end]);
        $giftCount = (clone $giftRedemptions)->count();
        $giftNsp = (float) (clone $giftRedemptions)->where('currency', 'NSP')->sum('value');
        $giftUsd = (float) (clone $giftRedemptions)->where('currency', 'USD')->sum('value');

        // ═══════════════════════════════════════════════════════
        //  الإحالات
        // ═══════════════════════════════════════════════════════
        $referralRewards = ReferralReward::whereBetween('paid_at', [$start, $end])
            ->where('status', 'paid');
        $referralCount = (clone $referralRewards)->count();
        $referralNsp = (float) (clone $referralRewards)->where('currency', 'NSP')->sum('amount');
        $referralUsd = (float) (clone $referralRewards)->where('currency', 'USD')->sum('amount');

        // ═══════════════════════════════════════════════════════
        //  المستخدمون
        // ═══════════════════════════════════════════════════════
        $newUsers    = User::whereBetween('created_at', [$start, $end])->count();
        $activeUsers = (clone $completed)->distinct('user_id')->count('user_id');
        $totalUsers  = User::count();

        // ═══════════════════════════════════════════════════════
        //  التنبيهات
        // ═══════════════════════════════════════════════════════
        $pendingWithdrawals = Transaction::pending()->withdrawals()->count();
        $pendingDeposits    = Transaction::pending()->deposits()->count();
        $rejectedToday      = Transaction::where('status', Transaction::STATUS_REJECTED)
            ->whereBetween('rejected_at', [$start, $end])
            ->count();

        // ═══════════════════════════════════════════════════════
        //  المالية
        // ═══════════════════════════════════════════════════════
        $commissionNsp = (float) (clone $completed)->where('to_currency', 'NSP')->sum('commission_amount');
        $commissionUsd = (float) (clone $completed)->where('to_currency', 'USD')->sum('commission_amount');

        return [
            'date' => $date,

            // المستخدمون
            'new_users'           => $newUsers,
            'active_users'        => $activeUsers,
            'total_users'         => $totalUsers,

            // الإيداعات
            'deposits_count'      => (clone $deposits)->count(),
            'deposits_nsp'        => (float) (clone $deposits)->where('to_currency', 'NSP')->sum('amount_to'),
            'deposits_usd'        => (float) (clone $deposits)->where('to_currency', 'USD')->sum('amount_to'),

            // السحوبات
            'withdrawals_count'   => (clone $withdrawals)->count(),
            'withdrawals_nsp'     => (float) (clone $withdrawals)->where('from_currency', 'NSP')->sum('amount_from'),
            'withdrawals_usd'     => (float) (clone $withdrawals)->where('from_currency', 'USD')->sum('amount_from'),

            // المكافآت
            'bonus_count'         => (clone $bonuses)->count(),
            'bonus_nsp'           => (float) (clone $bonuses)->where('to_currency', 'NSP')->sum('amount_to'),
            'bonus_usd'           => (float) (clone $bonuses)->where('to_currency', 'USD')->sum('amount_to'),

            // iChancy
            'ichancy_deposits_count'  => (clone $ichancyDeposits)->count(),
            'ichancy_deposits_nsp'    => (float) (clone $ichancyDeposits)->sum('amount_to'),
            'ichancy_withdraws_count' => (clone $ichancyWithdraws)->count(),
            'ichancy_withdraws_nsp'   => (float) (clone $ichancyWithdraws)->sum('amount_from'),

            // Exchange
            'exchanges_count'     => $exchangesCount,

            // Admin operations
            'admin_credits'       => $adminCredits,
            'admin_debits'        => $adminDebits,

            // العجلة
            'wheel_spins'         => $wheelSpinsCount,
            'wheel_won_nsp'       => $wheelWonNsp,
            'wheel_won_usd'       => $wheelWonUsd,

            // الهدايا
            'gift_count'          => $giftCount,
            'gift_nsp'            => $giftNsp,
            'gift_usd'            => $giftUsd,

            // الإحالات
            'referral_count'      => $referralCount,
            'referral_nsp'        => $referralNsp,
            'referral_usd'        => $referralUsd,

            // العمولات
            'commission_nsp'      => $commissionNsp,
            'commission_usd'      => $commissionUsd,

            // التنبيهات
            'pending_withdrawals' => $pendingWithdrawals,
            'pending_deposits'    => $pendingDeposits,
            'rejected_today'      => $rejectedToday,
        ];
    }

    // ============================================================
    //  عرض في Terminal
    // ============================================================
    private function displayStats(array $s): void
    {
        $this->newLine();

        $rows = [
            ['📅 التاريخ', $s['date']->format('Y-m-d')],
            ['👥 مستخدمون جدد', $s['new_users']],
            ['🟢 مستخدمون نشطون', $s['active_users']],
            ['📈 إجمالي المستخدمين', $s['total_users']],
            ['', ''],
            ['📥 عدد الإيداعات', $s['deposits_count']],
            ['   💰 NSP', number_format($s['deposits_nsp'], 2)],
            ['   💵 USD', number_format($s['deposits_usd'], 2)],
            ['📤 عدد السحوبات', $s['withdrawals_count']],
            ['   💰 NSP', number_format($s['withdrawals_nsp'], 2)],
            ['   💵 USD', number_format($s['withdrawals_usd'], 2)],
            ['', ''],
            ['🎁 مكافآت الإيداع NSP', number_format($s['bonus_nsp'], 2)],
            ['🎁 مكافآت الإيداع USD', number_format($s['bonus_usd'], 2)],
        ];

        if ($s['ichancy_deposits_count'] > 0 || $s['ichancy_withdraws_count'] > 0) {
            $rows[] = ['🎮 iChancy (إيداع)', number_format($s['ichancy_deposits_nsp'], 2) . ' NSP'];
            $rows[] = ['🎮 iChancy (سحب)', number_format($s['ichancy_withdraws_nsp'], 2) . ' NSP'];
        }

        if ($s['exchanges_count'] > 0) {
            $rows[] = ['💱 التحويلات', $s['exchanges_count']];
        }

        if ($s['admin_credits'] > 0 || $s['admin_debits'] > 0) {
            $rows[] = ['👮 عمليات الأدمن', '+' . number_format($s['admin_credits'], 2) . ' / -' . number_format($s['admin_debits'], 2)];
        }

        $rows[] = ['', ''];

        if ($s['wheel_spins'] > 0) {
            $rows[] = ['🎡 لفات العجلة', $s['wheel_spins']];
            $rows[] = ['   💰 NSP جوائز', number_format($s['wheel_won_nsp'], 2)];
            $rows[] = ['   💵 USD جوائز', number_format($s['wheel_won_usd'], 2)];
        }

        if ($s['gift_count'] > 0) {
            $rows[] = ['🎁 أكواد هدايا', $s['gift_count']];
            $rows[] = ['   💰 NSP', number_format($s['gift_nsp'], 2)];
            $rows[] = ['   💵 USD', number_format($s['gift_usd'], 2)];
        }

        if ($s['referral_count'] > 0) {
            $rows[] = ['🤝 إحالات', $s['referral_count']];
            $rows[] = ['   💰 NSP', number_format($s['referral_nsp'], 2)];
            $rows[] = ['   💵 USD', number_format($s['referral_usd'], 2)];
        }

        $rows[] = ['', ''];
        $rows[] = ['💰 عمولات NSP', number_format($s['commission_nsp'], 2)];
        $rows[] = ['💰 عمولات USD', number_format($s['commission_usd'], 2)];
        $rows[] = ['', ''];
        $rows[] = ['⚠️ سحوبات معلقة', $s['pending_withdrawals']];
        $rows[] = ['⚠️ إيداعات معلقة', $s['pending_deposits']];
        $rows[] = ['🔴 مرفوضة اليوم', $s['rejected_today']];

        $this->table(['المؤشر', 'القيمة'], $rows);
    }

    // ============================================================
    //  الإرسال
    // ============================================================
    private function sendToChannel(array $stats): void
    {
        try {
            $bot = app(Nutgram::class);
            $service = app(NotificationService::class);

            $result = $service->notifyTransactionsChannel(
                $bot,
                $this->buildReport($stats),
            );

            if ($result) {
                $this->info('📢 تم إرسال التقرير للقناة.');
            } else {
                $this->warn('⚠️ فشل الإرسال.');
            }
        } catch (\Throwable $e) {
            $this->error('❌ ' . $e->getMessage());
            Log::warning('DailyFullReport: send failed', ['error' => $e->getMessage()]);
        }
    }

    private function sendToAdmins(array $stats): void
    {
        $admins = User::where('is_admin', true)->where('is_active', true)->get();

        if ($admins->isEmpty()) {
            return;
        }

        $bot = app(Nutgram::class);
        $text = $this->buildReport($stats);

        foreach ($admins as $admin) {
            if (empty($admin->telegram_id)) continue;

            try {
                $bot->sendMessage(
                    text: $text,
                    chat_id: $admin->telegram_id,
                    parse_mode: 'HTML',
                );
            } catch (\Throwable $e) {
                Log::warning('Admin report send failed', [
                    'admin_id' => $admin->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }

    // ============================================================
    //  بناء التقرير
    // ============================================================
    private function buildReport(array $s): string
    {
        $date = $s['date']->format('Y-m-d');

        $lines = [
            '📊 <b>تقرير بوت VEXORA الشامل</b>',
            '📅 ' . $date,
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👥 <b>المستخدمون:</b>',
            '├── 🆕 جدد: <b>' . $s['new_users'] . '</b>',
            '├── 🟢 نشطون: <b>' . $s['active_users'] . '</b>',
            '└── 📈 الإجمالي: <b>' . $s['total_users'] . '</b>',
            '',
            '💰 <b>المالية:</b>',
            '',
            '📥 <b>الإيداعات:</b>',
            '├── 💰 NSP: <b>' . number_format($s['deposits_nsp'], 2) . '</b> (' . $s['deposits_count'] . ')',
            '└── 💵 USD: <b>' . number_format($s['deposits_usd'], 2) . '</b>',
            '',
            '📤 <b>السحوبات:</b>',
            '├── 💰 NSP: <b>' . number_format($s['withdrawals_nsp'], 2) . '</b> (' . $s['withdrawals_count'] . ')',
            '└── 💵 USD: <b>' . number_format($s['withdrawals_usd'], 2) . '</b>',
            '',
            '🎁 <b>مكافآت الإيداع:</b>',
            '├── 💰 NSP: <b>' . number_format($s['bonus_nsp'], 2) . '</b> (' . $s['bonus_count'] . ')',
            '└── 💵 USD: <b>' . number_format($s['bonus_usd'], 2) . '</b>',
            '',
        ];

        // iChancy (إن وُجد)
        if ($s['ichancy_deposits_count'] > 0 || $s['ichancy_withdraws_count'] > 0) {
            $lines[] = '🎮 <b>iChancy:</b>';
            $lines[] = '├── 📥 إيداع: <b>' . number_format($s['ichancy_deposits_nsp'], 2) . '</b> NSP (' . $s['ichancy_deposits_count'] . ')';
            $lines[] = '└── 📤 سحب: <b>' . number_format($s['ichancy_withdraws_nsp'], 2) . '</b> NSP (' . $s['ichancy_withdraws_count'] . ')';
            $lines[] = '';
        }

        // Exchange
        if ($s['exchanges_count'] > 0) {
            $lines[] = '💱 <b>التحويلات:</b> <b>' . $s['exchanges_count'] . '</b> عملية';
            $lines[] = '';
        }

        // Admin operations
        if ($s['admin_credits'] > 0 || $s['admin_debits'] > 0) {
            $lines[] = '👮 <b>عمليات الأدمن:</b>';
            if ($s['admin_credits'] > 0) {
                $lines[] = '├── ➕ إضافات: <b>' . number_format($s['admin_credits'], 2) . '</b>';
            }
            if ($s['admin_debits'] > 0) {
                $lines[] = '└── ➖ خصومات: <b>' . number_format($s['admin_debits'], 2) . '</b>';
            }
            $lines[] = '';
        }

        // العجلة
        if ($s['wheel_spins'] > 0) {
            $lines[] = '🎡 <b>عجلة الحظ:</b>';
            $lines[] = '├── 🔄 لفات: <b>' . $s['wheel_spins'] . '</b>';
            $lines[] = '├── 💰 جوائز NSP: <b>' . number_format($s['wheel_won_nsp'], 2) . '</b>';
            $lines[] = '└── 💵 جوائز USD: <b>' . number_format($s['wheel_won_usd'], 2) . '</b>';
            $lines[] = '';
        }

        // الهدايا
        if ($s['gift_count'] > 0) {
            $lines[] = '🎁 <b>أكواد الهدايا:</b>';
            $lines[] = '├── 🎫 مُستخدَمة: <b>' . $s['gift_count'] . '</b>';
            $lines[] = '├── 💰 NSP: <b>' . number_format($s['gift_nsp'], 2) . '</b>';
            $lines[] = '└── 💵 USD: <b>' . number_format($s['gift_usd'], 2) . '</b>';
            $lines[] = '';
        }

        // الإحالات
        if ($s['referral_count'] > 0) {
            $lines[] = '🤝 <b>الإحالات:</b>';
            $lines[] = '├── 🆕 جديدة: <b>' . $s['referral_count'] . '</b>';
            $lines[] = '├── 💰 مكافآت NSP: <b>' . number_format($s['referral_nsp'], 2) . '</b>';
            $lines[] = '└── 💵 مكافآت USD: <b>' . number_format($s['referral_usd'], 2) . '</b>';
            $lines[] = '';
        }

        // العمولات
        $lines[] = '━━━━━━━━━━━━━━━━━━';
        $lines[] = '';
        $lines[] = '💰 <b>العمولات:</b>';
        $lines[] = '├── 💰 NSP: <b>' . number_format($s['commission_nsp'], 2) . '</b>';
        $lines[] = '└── 💵 USD: <b>' . number_format($s['commission_usd'], 2) . '</b>';
        $lines[] = '';

        // التنبيهات
        $hasAlerts = $s['pending_withdrawals'] > 0
            || $s['pending_deposits'] > 0
            || $s['rejected_today'] > 0;

        if ($hasAlerts) {
            $lines[] = '━━━━━━━━━━━━━━━━━━';
            $lines[] = '';
            $lines[] = '⚠️ <b>تنبيهات:</b>';

            if ($s['pending_withdrawals'] > 0) {
                $lines[] = '├── 🟡 سحوبات معلقة: <b>' . $s['pending_withdrawals'] . '</b>';
            }
            if ($s['pending_deposits'] > 0) {
                $lines[] = '├── 🟡 إيداعات معلقة: <b>' . $s['pending_deposits'] . '</b>';
            }
            if ($s['rejected_today'] > 0) {
                $lines[] = '└── 🔴 مرفوضة اليوم: <b>' . $s['rejected_today'] . '</b>';
            }
            $lines[] = '';
        }

        $lines[] = '━━━━━━━━━━━━━━━━━━';
        $lines[] = '';
        $lines[] = '📅 ' . now()->format('Y-m-d H:i:s');

        return implode("\n", $lines);
    }
}
