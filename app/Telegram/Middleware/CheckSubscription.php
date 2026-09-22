<?php

namespace App\Telegram\Middleware;

use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class CheckSubscription
{
    public function __invoke(Nutgram $bot, $next): void
    {
        // ─── 1. تجاهل الأوامر ───
        $text = $bot->message()?->text ?? '';

        if (str_starts_with($text, '/start') || str_starts_with($text, '/check')) {
            $next($bot);
            return;
        }

        // ─── 2. تجاهل callback التحقق ───
        if ($bot->isCallbackQuery()) {
            $data = $bot->callbackQuery()?->data ?? '';

            if (str_starts_with($data, 'check.subscription')) {
                $next($bot);
                return;
            }
        }

        // ─── 3. التحقق ───
        try {
            $service = app(SubscriptionService::class);

            if ($service->isSubscribed($bot->userId())) {
                $next($bot);
                return;
            }

            // ─── 4. اعرض رسالة الاشتراك ───
            $service->sendSubscriptionMessage($bot);

            // ⛔ امنع الوصول — لا نستدعي $next
            return;
        } catch (\Throwable $e) {
            Log::warning('CheckSubscription failed', [
                'telegram_id' => $bot->userId(),
                'error'       => $e->getMessage(),
            ]);

            // ⚠️ إذا فشل — اسمح بالمرور
            $next($bot);
        }
    }
}
