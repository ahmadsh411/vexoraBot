<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class NotifyPendingWithdraws extends Command
{
    protected $signature = 'notify:pending
                            {--hours=6 : الحد الأدنى لعمر الطلب}
                            {--force : إرسال حتى لو لا توجد طلبات}';

    protected $description = 'تذكير الأدمن بالسحوبات المعلقة';

    public function handle(): int
    {
        $hours = (int) ($this->option('hours') ?? 6);
        $cutoff = now()->subHours($hours);

        $this->info("🔔 فحص السحوبات المعلقة (أقدم من {$hours} ساعة)...");
        $this->newLine();

        $pending = Transaction::pending()
            ->withdrawals()
            ->where('created_at', '<', $cutoff)
            ->with('user')
            ->orderBy('created_at')
            ->get();

        if ($pending->isEmpty()) {
            $this->info('✅ لا توجد سحوبات معلقة.');
            return self::SUCCESS;
        }

        $this->warn("⚠️  عدد السحوبات المعلقة: {$pending->count()}");
        $this->newLine();

        // ─── جدول ───
        $rows = $pending->map(fn($tx) => [
            '#' . $tx->id,
            $tx->user?->username ?? 'غير معروف',
            number_format((float) $tx->amount_from, 2) . ' ' . $tx->from_currency,
            $tx->created_at->diffForHumans(),
        ])->toArray();

        $this->table(
            ['المعرف', 'المستخدم', 'المبلغ', 'منذ'],
            $rows,
        );

        $this->newLine();

        // ─── إرسال إشعار للأدمن ───
        $this->sendNotification($pending);

        return self::SUCCESS;
    }

    // ============================================================
    //  إرسال الإشعار
    // ============================================================
    private function sendNotification($pending): void
    {
        try {
            $bot = app(Nutgram::class);
            $totalNsp = $pending->where('from_currency', 'NSP')->sum('amount_from');
            $totalUsd = $pending->where('from_currency', 'USD')->sum('amount_from');

            $text = implode("\n", [
                '🔔 <b>تذكير: سحوبات معلقة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '⚠️ <b>لديك ' . $pending->count() . ' سحب معلق</b>',
                '',
                '💰 <b>الإجمالي:</b>',
                '├── NSP: <b>' . number_format((float) $totalNsp, 2) . '</b>',
                '└── USD: <b>' . number_format((float) $totalUsd, 2) . '</b>',
                '',
                '⏰ <b>أقدم طلب:</b>',
                '└── منذ ' . $pending->first()->created_at->diffForHumans(),
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '💡 <i>يرجى معالجة الطلبات في أقرب وقت</i>',
            ]);

            // ─── إرسال لكل أدمن ───
            $admins = User::where('is_admin', true)
                ->where('is_active', true)
                ->whereNotNull('telegram_id')
                ->get();

            $sent = 0;

            foreach ($admins as $admin) {
                try {
                    $bot->sendMessage(
                        text: $text,
                        chat_id: $admin->telegram_id,
                        parse_mode: 'HTML',
                    );
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning('Admin notification failed', [
                        'admin_id' => $admin->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            // ─── إرسال لقناة المعاملات ───
            try {
                app(NotificationService::class)->notifyTransactionsChannel($bot, $text);
            } catch (\Throwable $e) {
            }

            $this->info("✅ تم إرسال الإشعار لـ {$sent} أدمن.");

            Log::info('Pending withdraws notified', [
                'count' => $pending->count(),
                'admins_notified' => $sent,
            ]);
        } catch (\Throwable $e) {
            $this->error('❌ فشل الإرسال: ' . $e->getMessage());
            Log::error('Notify pending failed', ['error' => $e->getMessage()]);
        }
    }
}
