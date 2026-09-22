<?php

namespace App\Console\Commands;

use App\Services\IChancy\IChancyService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class MonitorIChancyBalance extends Command
{
    protected $signature = 'ichancy:monitor-balance
                            {--threshold=200000 : الحد الأدنى لرصيد الكاشير}
                            {--cooldown=360 : دقائق بين التذكيرات}
                            {--force : إرسال حتى لو مُرسل سابقاً}';

    protected $description = 'مراقبة رصيد الكاشير وإشعار القناة العامة عند انخفاضه';

    // مفاتيح الحالة
    private const CACHE_KEY_STATE      = 'ichancy.cashier.state';       // normal | low
    private const CACHE_KEY_LAST_ALERT = 'ichancy.cashier.last_alert';  // timestamp

    public function handle(): int
    {
        $threshold = (float) $this->option('threshold');
        $cooldown  = (int)   $this->option('cooldown');
        $force     = (bool)  $this->option('force');

        $this->info('🔍 فحص رصيد الكاشير...');
        $this->line('   الحد الأدنى: ' . number_format($threshold, 2) . ' NSP');
        $this->newLine();

        // ═══ 1. جلب كل المحافظ ═══
        $response = app(IChancyService::class)->getAgentAllWallets();

        if (! $response || ! ($response['status'] ?? false)) {
            $this->error('❌ فشل جلب المحافظ من IChancy');
            Log::warning('MonitorIChancyBalance: cannot fetch wallets', [
                'response' => $response,
            ]);
            return self::FAILURE;
        }

        $wallets = $response['result'] ?? [];

        if (empty($wallets)) {
            $this->error('❌ لا توجد محافظ');
            return self::FAILURE;
        }

        // ═══ 2. المحفظة الأولى (NSP) ═══
        $wallet = $wallets[0];

        // ✅ الرصيد الحقيقي = currentWallet
        $balance        = (float) ($wallet['currentWallet'] ?? 0);
        $available      = (float) ($wallet['availableWallet'] ?? 0); // للعرض فقط
        $currency       = $wallet['currencyCode'] ?? 'NSP';
        $currencyName   = $wallet['currencyName'] ?? '—';

        $this->line('   العملة: ' . $currency . ' (' . $currencyName . ')');
        $this->line('   💰 رصيد الكاشير: ' . number_format($balance, 2) . ' ' . $currency);
        $this->line('   📊 المتاح (خط ائتمان): ' . number_format($available, 2) . ' ' . $currency);
        $this->newLine();

        // ═══ 3. المنطق ═══
        $isLow         = $balance <= $threshold;
        $previousState = Cache::get(self::CACHE_KEY_STATE, 'normal');

        // ─── (أ) normal → low ───
        if ($isLow && $previousState === 'normal') {
            $this->sendLowBalanceAlert($balance, $threshold, $currency, isReminder: false);
            Cache::put(self::CACHE_KEY_STATE, 'low', now()->addDays(30));
            Cache::put(self::CACHE_KEY_LAST_ALERT, now()->timestamp, now()->addDays(30));
            $this->warn('⚠️  تم إرسال تنبيه: الرصيد منخفض');
            return self::SUCCESS;
        }

        // ─── (ب) low → normal (تعافي) ───
        if (! $isLow && $previousState === 'low') {
            $this->sendRecoveryAlert($balance, $currency);
            Cache::put(self::CACHE_KEY_STATE, 'normal', now()->addDays(30));
            $this->info('✅ تم إرسال إشعار: الرصيد عاد طبيعياً');
            return self::SUCCESS;
        }

        // ─── (ج) لا يزال منخفضاً (Cooldown / Reminder) ───
        if ($isLow && $previousState === 'low') {
            $lastAlert    = (int) Cache::get(self::CACHE_KEY_LAST_ALERT, 0);
            $minutesSince = (time() - $lastAlert) / 60;

            if ($force || $minutesSince >= $cooldown) {
                $this->sendLowBalanceAlert($balance, $threshold, $currency, isReminder: true);
                Cache::put(self::CACHE_KEY_LAST_ALERT, now()->timestamp, now()->addDays(30));
                $this->warn('⚠️  تم إرسال تذكير');
                return self::SUCCESS;
            }

            $remaining = (int) round($cooldown - $minutesSince);
            $this->line('   ⏳ فترة الانتظار (متبقٍ ' . $remaining . ' دقيقة)');
            return self::SUCCESS;
        }

        $this->info('✅ الرصيد طبيعي.');
        return self::SUCCESS;
    }

    // ════════════════════════════════════════════════════════════
    //  تنبيه الرصيد المنخفض → القناة العامة
    // ════════════════════════════════════════════════════════════
    private function sendLowBalanceAlert(
        float $balance,
        float $threshold,
        string $currency,
        bool $isReminder = false,
    ): void {
        $bot = app(Nutgram::class);

        $title = $isReminder
            ? '🔔 <b>تذكير: رصيد الكاشير منخفض</b>'
            : '🚨 <b>تنبيه: رصيد الكاشير منخفض</b>';

        $deficit = max(0, $threshold - $balance);

        $text = implode("\n", [
            $title,
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💵 <b>رصيد الكاشير الحالي:</b>',
            '└── <code>' . number_format($balance, 2) . ' ' . $currency . '</code>',
            '',
            '⚠️ <b>أقل من الحد الأدنى:</b>',
            '└── <code>' . number_format($threshold, 2) . ' ' . $currency . '</code>',
            '',
            '📉 <b>العجز:</b> <code>' . number_format($deficit, 2) . ' ' . $currency . '</code>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💳 <b>يجب شحن الكاشير في أقرب وقت!</b>',
            '',
            '📅 ' . now()->format('Y-m-d H:i:s'),
        ]);

        try {
            app(NotificationService::class)->notifyGeneralChannel($bot, $text);

            Log::info('MonitorIChancyBalance: low balance alert sent', [
                'balance'     => $balance,
                'threshold'   => $threshold,
                'currency'    => $currency,
                'is_reminder' => $isReminder,
            ]);
        } catch (\Throwable $e) {
            Log::error('MonitorIChancyBalance: failed to send alert', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ════════════════════════════════════════════════════════════
    //  إشعار عودة الرصيد → القناة العامة
    // ════════════════════════════════════════════════════════════
    private function sendRecoveryAlert(float $balance, string $currency): void
    {
        $bot = app(Nutgram::class);

        $text = implode("\n", [
            '✅ <b>رصيد الكاشير عاد طبيعياً</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💵 <b>الرصيد الحالي:</b>',
            '└── <code>' . number_format($balance, 2) . ' ' . $currency . '</code>',
            '',
            '👍 يمكنك متابعة العمل بشكل طبيعي.',
            '',
            '📅 ' . now()->format('Y-m-d H:i:s'),
        ]);

        try {
            app(NotificationService::class)->notifyGeneralChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::error('MonitorIChancyBalance: recovery failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
