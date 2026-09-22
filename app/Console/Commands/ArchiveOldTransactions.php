<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ArchiveOldTransactions extends Command
{
    protected $signature = 'transactions:archive
                            {--days=60 : العمر المطلوب للأرشفة}
                            {--dry-run : عرض فقط}
                            {--force : بدون تأكيد}';

    protected $description = 'أرشفة التقارير القديمة (Soft Delete بعد 60 يوم)';

    public function handle(): int
    {
        $days      = (int) ($this->option('days') ?? 60);
        $dryRun    = (bool) $this->option('dry-run');
        $force     = (bool) $this->option('force');
        $cutoff    = Carbon::now()->subDays($days);

        $this->info("📦 بدء أرشفة التقارير الأقدم من {$days} يوم");
        $this->info("📅 تاريخ القطع: {$cutoff->format('Y-m-d H:i:s')}");
        $this->newLine();

        $query = Transaction::query()
            ->where('created_at', '<', $cutoff)
            ->whereIn('status', [
                Transaction::STATUS_COMPLETED,
                Transaction::STATUS_REJECTED,
                Transaction::STATUS_CANCELLED,
                Transaction::STATUS_FAILED,
            ]);

        $count = $query->count();

        if ($count === 0) {
            $this->info('✅ لا توجد تقارير للأرشفة.');
            return self::SUCCESS;
        }

        $this->warn("📊 عدد التقارير: {$count}");

        $this->table(
            ['النوع', 'العدد'],
            [
                ['📥 إيداع', (clone $query)->deposits()->count()],
                ['📤 سحب', (clone $query)->withdrawals()->count()],
                ['💱 تحويل', (clone $query)->where('type', Transaction::TYPE_EXCHANGE)->count()],
                ['➕ إداري', (clone $query)->adjustments()->count()],
            ],
        );

        $this->newLine();

        if ($dryRun) {
            $this->info('🔍 Dry Run — لم يتم أرشفة أي شيء.');
            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm("⚠️  هل أنت متأكد من أرشفة {$count} تقرير؟")) {
            $this->info('❌ تم الإلغاء.');
            return self::SUCCESS;
        }

        $this->info('📦 جاري الأرشفة...');

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $archived = 0;

        $query->chunkById(100, function ($transactions) use (&$archived, $bar) {
            foreach ($transactions as $transaction) {
                $transaction->delete();
                $archived++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        Log::info('Transactions archived', [
            'archived' => $archived,
            'days'     => $days,
            'cutoff'   => $cutoff->toDateTimeString(),
        ]);

        $this->info("✅ تم أرشفة {$archived} تقرير.");

        $this->notifyTransactionsChannel($archived, $cutoff);

        return self::SUCCESS;
    }

    private function notifyChannel(int $count, Carbon $cutoff): void
    {
        try {
            $channelId = config('services.telegram.users_channel_id');
            if (empty($channelId)) return;

            $bot = app(\SergiX44\Nutgram\Nutgram::class);

            $bot->sendMessage(
                text: implode("\n", [
                    '📦 <b>أرشفة تلقائية</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '📊 تم أرشفة <b>' . $count . '</b> تقرير.',
                    '📅 الأقدم من: ' . $cutoff->format('Y-m-d'),
                    '',
                    '🗑️ سيتم حذفهم نهائياً بعد 90 يوم.',
                ]),
                chat_id: $channelId,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            Log::warning('Archive notification failed', ['error' => $e->getMessage()]);
        }
    }
}
