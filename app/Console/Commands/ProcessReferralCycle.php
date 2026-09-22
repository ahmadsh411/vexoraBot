<?php

namespace App\Console\Commands;

use App\Models\ReferralCycle;
use App\Services\ReferralCycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessReferralCycle extends Command
{
    protected $signature = 'referral:process-cycle
                            {--force : إغلاق الدورة حتى لو لم تنته}
                            {--dry-run : عرض فقط}';

    protected $description = 'معالجة دورة الإحالة';

    public function handle(ReferralCycleService $service): int
    {
        $this->info('🎯 بدء معالجة دورة الإحالة');
        $this->newLine();

        $cycle = ReferralCycle::open()->latestFirst()->first();

        if (! $cycle) {
            $this->warn('⚠️  لا توجد دورة مفتوحة — إنشاء دورة جديدة');
            $cycle = $service->getOrCreateCurrentCycle();
            $this->info("✅ دورة جديدة: {$cycle->duration_label}");
            return self::SUCCESS;
        }

        if (! $cycle->hasEnded() && ! $this->option('force')) {
            $this->warn('⚠️  الدورة الحالية لم تنته بعد');
            $this->line("   تنتهي في: {$cycle->end_date->format('Y-m-d')}");
            $this->line("   الأيام المتبقية: {$cycle->daysRemaining()}");
            return self::SUCCESS;
        }

        $this->info("📊 الدورة: {$cycle->duration_label}");
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('🔍 Dry Run — لم يتم التنفيذ');
            return self::SUCCESS;
        }

        try {
            $result = $service->processCycle($cycle);

            $this->newLine();
            $this->info('✅ تمت المعالجة');
            $this->table(
                ['المؤشر', 'القيمة'],
                [
                    ['عدد المُحيلين',   $result['referrers_count']],
                    ['إجمالي المكافآت', number_format($result['total_rewards'], 2) . ' NSP'],
                ],
            );

            $newCycle = $service->getOrCreateCurrentCycle();
            $this->newLine();
            $this->info("🆕 دورة جديدة: {$newCycle->duration_label}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("❌ فشل: {$e->getMessage()}");

            Log::error('Cycle processing failed', [
                'cycle_id' => $cycle->id,
                'error'    => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
