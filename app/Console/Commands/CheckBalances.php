<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckBalances extends Command
{
    protected $signature = 'balance:check
                            {--threshold=1 : حد التنبيه بالفرق}
                            {--fix : محاولة الإصلاح}
                            {--detailed : عرض تفصيلي}';

    protected $description = 'فحص تناسق الأرصدة بين المحافظ والمعاملات';

    public function handle(): int
    {
        $this->info('💰 فحص تناسق الأرصدة');
        $this->newLine();

        // ═══════════════════════════════════════════════════════
        //  [1] فحص المحفظة الرئيسية
        // ═══════════════════════════════════════════════════════
        $this->checkMainWallet();

        $this->newLine();

        // ═══════════════════════════════════════════════════════
        //  [2] فحص محافظ المستخدمين
        // ═══════════════════════════════════════════════════════
        $this->checkUserWallets();

        $this->newLine();

        // ═══════════════════════════════════════════════════════
        //  [3] فحص المعاملات المعلقة القديمة
        // ═══════════════════════════════════════════════════════
        $this->checkStaleTransactions();

        return self::SUCCESS;
    }

    // ============================================================
    //  [1] المحفظة الرئيسية
    // ============================================================
    private function checkMainWallet(): void
    {
        $this->info('🏦 [1] المحفظة الرئيسية');

        $mainWallet = Wallet::where('type', Wallet::TYPE_MAIN)->first();

        if (! $mainWallet) {
            $this->error('  ❌ لا توجد محفظة رئيسية!');
            return;
        }

        $this->line("  💰 رصيد NSP: " . number_format((float) $mainWallet->balance_nsp, 2));
        $this->line("  💵 رصيد USD: " . number_format((float) $mainWallet->balance_usd, 2));

        // ─── المقارنة مع مجموع محافظ المستخدمين ───
        $userTotalNsp = Wallet::where('type', Wallet::TYPE_USER)->sum('balance_nsp');
        $userTotalUsd = Wallet::where('type', Wallet::TYPE_USER)->sum('balance_usd');

        $this->newLine();
        $this->line("  📊 مجموع محافظ المستخدمين:");
        $this->line("     💰 NSP: " . number_format((float) $userTotalNsp, 2));
        $this->line("     💵 USD: " . number_format((float) $userTotalUsd, 2));

        $this->newLine();
        $this->line("  🔍 المحفظة الرئيسية يجب أن تحتوي على الأقل: ");
        $this->line("     💰 NSP: " . number_format((float) $userTotalNsp, 2));
        $this->line("     💵 USD: " . number_format((float) $userTotalUsd, 2));

        $diffNsp = (float) $mainWallet->balance_nsp - (float) $userTotalNsp;
        $diffUsd = (float) $mainWallet->balance_usd - (float) $userTotalUsd;

        if ($diffNsp < 0 || $diffUsd < 0) {
            $this->error("  ⚠️  تحذير: المحفظة الرئيسية لا تكفي!");
            $this->error("     العجز NSP: " . number_format($diffNsp, 2));
            $this->error("     العجز USD: " . number_format($diffUsd, 2));

            Log::critical('Main wallet insufficient', [
                'deficit_nsp' => $diffNsp,
                'deficit_usd' => $diffUsd,
            ]);
        } else {
            $this->info("  ✅ المحفظة الرئيسية سليمة");
            $this->line("     فائض NSP: " . number_format($diffNsp, 2));
            $this->line("     فائض USD: " . number_format($diffUsd, 2));
        }
    }

    // ============================================================
    //  [2] محافظ المستخدمين
    // ============================================================
    private function checkUserWallets(): void
    {
        $this->info('👥 [2] محافظ المستخدمين');

        $negativeNsp = Wallet::where('type', Wallet::TYPE_USER)
            ->where('balance_nsp', '<', 0)
            ->count();

        $negativeUsd = Wallet::where('type', Wallet::TYPE_USER)
            ->where('balance_usd', '<', 0)
            ->count();

        if ($negativeNsp > 0 || $negativeUsd > 0) {
            $this->error("  ❌ محافظ بأرصدة سالبة:");
            $this->error("     NSP: {$negativeNsp}");
            $this->error("     USD: {$negativeUsd}");

            Log::critical('Negative wallets found', [
                'nsp' => $negativeNsp,
                'usd' => $negativeUsd,
            ]);
        } else {
            $this->info("  ✅ لا توجد أرصدة سالبة");
        }

        $this->line("  📊 إجمالي المحافظ: " . Wallet::where('type', Wallet::TYPE_USER)->count());
    }

    // ============================================================
    //  [3] المعاملات المعلقة القديمة
    // ============================================================
    private function checkStaleTransactions(): void
    {
        $this->info('⏳ [3] المعاملات المعلقة القديمة');

        $staleDeposits = Transaction::pending()
            ->deposits()
            ->where('created_at', '<', now()->subDays(3))
            ->count();

        $staleWithdraws = Transaction::pending()
            ->withdrawals()
            ->where('created_at', '<', now()->subDays(3))
            ->count();

        if ($staleDeposits > 0 || $staleWithdraws > 0) {
            $this->warn("  ⚠️  معاملات معلقة أقدم من 3 أيام:");
            $this->warn("     إيداعات: {$staleDeposits}");
            $this->warn("     سحوبات: {$staleWithdraws}");
        } else {
            $this->info("  ✅ لا توجد معاملات معلقة قديمة");
        }
    }
}
