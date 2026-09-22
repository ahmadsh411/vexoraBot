<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\BroadcastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use SergiX44\Nutgram\Nutgram;

class BroadcastMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;
    public int $tries = 1;

    /**
     * Telegram global rate limit: ~30 messages/second
     * نستخدم 25 للأمان
     */
    private const RATE_LIMIT_MAX = 25;
    private const RATE_LIMIT_DECAY = 1;

    public function __construct(
        public readonly string $target,
        public readonly string $text,
        public readonly int $adminId,
    ) {}

    public function handle(Nutgram $bot, BroadcastService $broadcastService): void
    {
        $query = $this->buildQuery();
        $total = $query->count();

        if ($total === 0) {
            $this->notifyAdmin($bot, "⚠️ <b>لا يوجد مستخدمون في هذه الفئة.</b>");
            return;
        }

        Log::info('BroadcastMessageJob started', [
            'admin_id' => $this->adminId,
            'target'   => $this->target,
            'total'    => $total,
        ]);

        $sent = $failed = $blocked = 0;
        $startedAt = now();

        $query->chunkById(100, function ($users) use (
            $bot,
            &$sent,
            &$failed,
            &$blocked
        ) {
            foreach ($users as $user) {
                // ✅ انتظار ذكي — فقط عند الحاجة للـ rate limit
                $this->waitForRateLimitSlot();

                $result = $this->sendToUser($bot, $user);

                match ($result) {
                    'sent'    => $sent++,
                    'blocked' => $blocked++,
                    default   => $failed++,
                };
            }
        });

        $duration = $startedAt->diffInSeconds(now());

        $this->notifyAdmin($bot, implode("\n", [
            '✅ <b>اكتمل البث</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>النتائج:</b>',
            '├── ✅ نجح: <b>' . $sent . '</b>',
            '├── ❌ فشل: <b>' . $failed . '</b>',
            '└── 🚫 محظور: <b>' . $blocked . '</b>',
            '',
            '🎯 <b>الفئة:</b> ' . $broadcastService->targetLabel($this->target),
            '👥 <b>الإجمالي:</b> <b>' . $total . '</b>',
            '⏱ <b>المدة:</b> ' . $duration . ' ثانية',
        ]));

        Log::info('BroadcastMessageJob completed', [
            'admin_id' => $this->adminId,
            'target'   => $this->target,
            'total'    => $total,
            'sent'     => $sent,
            'failed'   => $failed,
            'blocked'  => $blocked,
            'duration' => $duration,
        ]);
    }

    // ============================================================
    //  Rate Limiting — ذكي (ينتظر فقط عند الحاجة)
    // ============================================================

    private function waitForRateLimitSlot(): void
    {
        $key = 'tg-broadcast:rate';
        $maxWait = 5; // ثواني كحد أقصى للانتظار
        $started = microtime(true);

        while (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_MAX)) {
            if (microtime(true) - $started > $maxWait) {
                Log::warning('Broadcast: rate limit wait exceeded');
                break;
            }
            usleep(50000); // 50ms
        }

        RateLimiter::hit($key, self::RATE_LIMIT_DECAY);
    }

    // ============================================================
    //  بناء الاستعلام
    // ============================================================

    private function buildQuery()
    {
        return match ($this->target) {
            'balance_nsp' => User::whereNotNull('telegram_id')
                ->whereHas('wallet', fn($q) => $q->where('balance_nsp', '>', 0)),

            'balance_usd' => User::whereNotNull('telegram_id')
                ->whereHas('wallet', fn($q) => $q->where('balance_usd', '>', 0)),

            'referrers' => User::whereNotNull('telegram_id')
                ->where('referrals_count', '>', 0),

            'new' => User::whereNotNull('telegram_id')
                ->where('created_at', '>=', now()->subDays(7)),

            'inactive' => User::whereNotNull('telegram_id')
                ->where(function ($q) {
                    $q->whereNull('last_login_at')
                        ->orWhere('last_login_at', '<', now()->subDays(30));
                }),

            'has_account' => User::whereNotNull('telegram_id')
                ->has('paymentAccounts'),

            default => User::whereNotNull('telegram_id')
                ->where('is_active', true)
                ->where('is_admin', false),
        };
    }

    // ============================================================
    //  الإرسال — بدون sleep(3) التعسفي
    // ============================================================

    private function sendToUser(Nutgram $bot, User $user): string
    {
        try {
            $bot->sendMessage(
                text: $this->text,
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );

            return 'sent';
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            if ($this->isBlocked($message)) {
                return 'blocked';
            }

            // 429 → استخرج retry_after من الاستجابة إن أمكن
            if (str_contains($message, '429') || str_contains($message, 'Too Many Requests')) {
                $wait = $this->extractRetryAfter($message, default: 3);
                sleep($wait);

                try {
                    $bot->sendMessage(
                        text: $this->text,
                        chat_id: $user->telegram_id,
                        parse_mode: 'HTML',
                    );
                    return 'sent';
                } catch (\Throwable $e2) {
                    Log::warning('Broadcast: retry after 429 failed', [
                        'user_id' => $user->id,
                        'error'   => $e2->getMessage(),
                    ]);
                    return 'failed';
                }
            }

            Log::warning('Broadcast: send failed', [
                'user_id' => $user->id,
                'error'   => $message,
            ]);

            return 'failed';
        }
    }

    /**
     * استخراج retry_after من رسالة خطأ Telegram
     */
    private function extractRetryAfter(string $message, int $default = 3): int
    {
        // تحاول استخراج: "retry after 5"
        if (preg_match('/retry after (\d+)/i', $message, $m)) {
            return min((int) $m[1], 60);
        }
        // محاولة ثانية: "retry_after":5
        if (preg_match('/retry_after["\']?\s*[:=]\s*(\d+)/i', $message, $m)) {
            return min((int) $m[1], 60);
        }
        return $default;
    }

    private function isBlocked(string $message): bool
    {
        return str_contains($message, 'blocked')
            || str_contains($message, 'user is deactivated')
            || str_contains($message, 'chat not found')
            || str_contains($message, 'bot was blocked');
    }

    private function notifyAdmin(Nutgram $bot, string $text): void
    {
        if ($this->adminId <= 0) {
            return; // إشعارات النظام بدون admin
        }

        try {
            $bot->sendMessage(
                text: $text,
                chat_id: $this->adminId,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            // تجاهل — لا نريد أن يفشل البث بسبب الإشعار
        }
    }
}
