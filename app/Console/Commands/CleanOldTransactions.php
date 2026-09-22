<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanOldTransactions extends Command
{
    protected $signature = 'transactions:clean
                            {--days=90 : العمر المطلوب في الأرشيف}
                            {--dry-run : عرض فقط}
                            {--force : بدون تأكيد}';

    protected $description = 'حذف الأرشيف القديم (Force Delete بعد 90 يوم)';

    public function handle(): int
    {
        $days   = (int) ($this->option('days') ?? 90);
        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');
        $cutoff = Carbon::now()->subDays($days);

        $this->info("🗑️  بدء حذف الأرشيف الأقدم من {$days} يوم");
        $this->info("📅 تاريخ القطع: {$cutoff->format('Y-m-d H:i:s')}");
        $this->newLine();

        $query = Transaction::onlyTrashed()
            ->where('deleted_at', '<', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info('✅ لا يوجد أرشيف للحذف.');
            return self::SUCCESS;
        }

        $this->warn("📊 عدد التقارير المؤرشفة: {$count}");

        if ($dryRun) {
            $this->info('🔍 Dry Run — لم يتم حذف أي شيء.');
            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm("⚠️  هل أنت متأكد من الحذف النهائي لـ {$count} تقرير؟")) {
            $this->info('❌ تم الإلغاء.');
            return self::SUCCESS;
        }

        $this->info('🗑️  جاري الحذف النهائي...');

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $deleted = 0;

        $query->chunkById(100, function ($transactions) use (&$deleted, $bar) {
            foreach ($transactions as $transaction) {
                $transaction->forceDelete();
                $deleted++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        Log::info('Archive cleaned', [
            'deleted' => $deleted,
            'days'    => $days,
            'cutoff'  => $cutoff->toDateTimeString(),
        ]);

        $this->info("✅ تم حذف {$deleted} تقرير نهائياً.");

        return self::SUCCESS;
    }
}
