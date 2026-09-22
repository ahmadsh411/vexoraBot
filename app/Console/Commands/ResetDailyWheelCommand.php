<?php

namespace App\Console\Commands;

use App\Models\WheelUserState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetDailyWheelCommand extends Command
{
    protected $signature = 'wheel:reset-daily
                            {--dry-run : عرض فقط}';

    protected $description = 'تصفير العدادات اليومية لعجلة الحظ';

    public function handle(): int
    {
        $this->info('🎡 بدء تصفير العدادات اليومية...');

        $query = WheelUserState::query()
            ->where('spins_today', '>', 0)
            ->where(function ($q) {
                $q->whereNull('last_spin_date')
                    ->orWhere('last_spin_date', '<', today());
            });

        $count = $query->count();

        if ($count === 0) {
            $this->info('✅ لا توجد عدادات للتصفير.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("🔍 Dry Run — {$count} سجل بحاجة للتصفير.");
            return self::SUCCESS;
        }

        $this->info("📊 تصفير {$count} سجل...");

        $updated = $query->update([
            'spins_today'    => 0,
            'last_spin_date' => null,
        ]);

        Log::info('Wheel daily reset', ['updated' => $updated]);

        $this->info("✅ تم تصفير {$updated} سجل.");

        return self::SUCCESS;
    }
}
