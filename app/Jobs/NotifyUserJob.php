<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class NotifyUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 5;

    public function __construct(
        public readonly int $userId,
        public readonly string $text,
    ) {}

    public function handle(Nutgram $bot): void
    {
        // ✅ select محدد — تقليل الذاكرة
        $user = User::query()
            ->select('id', 'telegram_id', 'is_active')
            ->find($this->userId);

        if (! $user || ! $user->telegram_id || ! $user->is_active) {
            return;
        }

        try {
            $bot->sendMessage(
                text: $this->text,
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );
            // ✅ حذفنا usleep — لا فائدة حقيقية
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            if ($this->isBlocked($message)) {
                Log::info('User blocked bot — skip', [
                    'user_id' => $user->id,
                ]);

                // ✅ علّم المستخدم لتفادي محاولات مستقبلية
                $user->update(['is_active' => false]);

                $this->delete();
                return;
            }

            Log::warning('NotifyUserJob failed', [
                'user_id' => $user->id,
                'error'   => $message,
            ]);

            throw $e;
        }
    }

    private function isBlocked(string $message): bool
    {
        return str_contains($message, 'blocked')
            || str_contains($message, 'chat not found')
            || str_contains($message, 'deactivated');
    }
}
