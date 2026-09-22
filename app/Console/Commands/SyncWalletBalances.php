<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncWalletBalances extends Command
{
    protected $signature = 'wallet:sync
                            {--dry-run : عرض فقط}
                            {--force : بدون تأكيد}';

    protected $description = 'مزامنة المحفظة الرئيسية مع مجموع محافظ المستخدمين';

    public function handle(): int
    {
        $this->info('🔄 مزامنة المحفظة الرئيسية');
        $this->newLine();

        $main = Wallet::where('type', Wallet::TYPE_MAIN)->first();

        if (! $main) {
            $this->error('❌ لا توجد محفظة رئيسية. شغّل: php artisan wallet:init');
            return self::FAILURE;
        }

        $userTotalNsp = (float) Wallet::where('type', Wallet::TYPE_USER)->sum('balance_nsp');
        $userTotalUsd = (float) Wallet::where('type', Wallet::TYPE_USER)->sum('balance_usd');

        $mainNsp = (float) $main->balance_nsp;
        $mainUsd = (float) $main->balance_usd;

        $diffNsp = $userTotalNsp - $mainNsp;
        $diffUsd = $userTotalUsd - $mainUsd;

        $this->table(
            ['المؤشر', 'NSP', 'USD'],
            [
                ['🏦 المحفظة الرئيسية', number_format($mainNsp, 2), number_format($mainUsd, 2)],
                ['👥 مجموع المستخدمين', number_format($userTotalNsp, 2), number_format($userTotalUsd, 2)],
                ['📊 الفرق', number_format($diffNsp, 2), number_format($diffUsd, 2)],
            ],
        );

        if (abs($diffNsp) < 0.01 && abs($diffUsd) < 0.01) {
            $this->info('✅ المحفظة الرئيسية متزامنة.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('⚠️  يوجد فرق يحتاج إلى تصحيح.');

        if ($this->option('dry-run')) {
            $this->info('🔍 Dry Run — لم يتم التعديل.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('هل تريد مزامنة المحفظة الرئيسية؟')) {
            $this->info('❌ تم الإلغاء.');
            return self::SUCCESS;
        }

        $main->update([
            'balance_nsp' => $userTotalNsp,
            'balance_usd' => $userTotalUsd,
        ]);

        Log::info('Main wallet synced', [
            'old_nsp' => $mainNsp,
            'new_nsp' => $userTotalNsp,
            'old_usd' => $mainUsd,
            'new_usd' => $userTotalUsd,
        ]);

        $this->info('✅ تم تحديث المحفظة الرئيسية.');
        $this->info("   💰 NSP: {$mainNsp} → {$userTotalNsp}");
        $this->info("   💵 USD: {$mainUsd} → {$userTotalUsd}");

        return self::SUCCESS;
    }
}
