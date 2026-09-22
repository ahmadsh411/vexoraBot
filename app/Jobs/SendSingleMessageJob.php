<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class SendSingleMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly string $telegramId,
        public readonly string $text,
        public readonly int $adminId,
    ) {}

    public function handle(Nutgram $bot): void
    {
        // ① الإرسال الفعلي — إن فشل، أعد الرمي
        try {
            $bot->sendMessage(
                text: $this->text,
                chat_id: $this->telegramId,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            $this->notifyAdmin(
                $bot,
                "❌ فشل الإرسال إلى <code>{$this->telegramId}</code>\n" .
                    '<code>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</code>'
            );

            throw $e;
        }

        // ② إشعار الإداري — معزول تماماً
        $this->notifyAdmin(
            $bot,
            "✅ تم الإرسال إلى <code>{$this->telegramId}</code>"
        );
    }

    private function notifyAdmin(Nutgram $bot, string $message): void
    {
        try {
            $bot->sendMessage(
                text: $message,
                chat_id: $this->adminId,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            Log::warning('SendSingleMessageJob: admin notification failed', [
                'admin_id' => $this->adminId,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
