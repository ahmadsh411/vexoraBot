<?php

namespace App\Telegram\Middleware;

use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use Illuminate\Support\Facades\Log;

class TrackRequest
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $requestId = (string) Str::uuid();
        request()->headers->set('X-Request-ID', $requestId);

        // ⚠️ فقط سجّل، ولا تستدعِ $next
        Log::info('TrackRequest called', [
            'callback_data' => $bot->callbackQuery()?->data,
        ]);

        // ✅ استدعِ $next مباشرة
        $next($bot);
    }
}
