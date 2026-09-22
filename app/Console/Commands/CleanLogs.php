<?php

namespace App\Console\Commands;

use App\Models\AdminAction;
use App\Models\SensitiveAccessLog;
use App\Models\TransactionAudit;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanLogs extends Command
{
    protected $signature = 'logs:clean
                            {--days=90 : عمر السجلات بالأيام}
                            {--dry-run : عرض فقط}
                            {--force : بدون تأكيد}';

    protected $description = 'تنظيف السجلات القديمة (AdminAction, SensitiveAccessLog, TransactionAudit)';

    public function handle(): int
    {
        $days   = (int) ($this->option('days') ?? 90);
        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');
        $cutoff = Carbon::now()->subDays($days);

        $this->info("🧹 تنظيف السجلات الأقدم من {$days} يوم");
        $this->info("📅 تاريخ القطع: {$cutoff->format('Y-m-d H:i:s')}");
        $this->newLine();

        $stats = [
            'AdminAction'        => AdminAction::where('created_at', '<', $cutoff)->count(),
            'SensitiveAccessLog' => SensitiveAccessLog::where('created_at', '<', $cutoff)->count(),
            'TransactionAudit'   => TransactionAudit::where('created_at', '<', $cutoff)->count(),
        ];

        $total = array_sum($stats);

        if ($total === 0) {
            $this->info('✅ لا توجد سجلات للتنظيف.');
            return self::SUCCESS;
        }

        $this->table(
            ['الجدول', 'العدد'],
            collect($stats)->map(fn($count, $table) => [$table, $count])->values()->toArray(),
        );

        $this->newLine();

        if ($dryRun) {
            $this->warn("🔍 Dry Run — لم يتم حذف أي شيء (إجمالي {$total}).");
            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm("⚠️  هل أنت متأكد من حذف {$total} سجل؟")) {
            $this->info('❌ تم الإلغاء.');
            return self::SUCCESS;
        }

        $this->info('🗑️  جاري الحذف...');
        $this->newLine();

        $deleted = [];

        foreach (['AdminAction', 'SensitiveAccessLog', 'TransactionAudit'] as $model) {
            $class = "App\\Models\\{$model}";
            $count = $class::where('created_at', '<', $cutoff)->delete();
            $deleted[$model] = $count;
            $this->line("  ✅ {$model}: {$count}");
        }

        $this->newLine();

        Log::info('Logs cleaned', [
            'deleted' => $deleted,
            'days'    => $days,
            'cutoff'  => $cutoff->toDateTimeString(),
        ]);

        $this->info('✅ تم التنظيف بنجاح.');

        return self::SUCCESS;
    }
}
