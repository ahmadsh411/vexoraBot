<?php

namespace App\Telegram\Middleware;

use Closure;
use Illuminate\Http\Request;
use SergiX44\Nutgram\Nutgram;

class TelegramOnlyPrivate
{
    public function handle(Request $request, Closure $next)
    {
        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);

        $chatType = $bot->chat()?->type;

        // تجاهل رسائل القنوات والمجموعات
        if (in_array($chatType, ['channel', 'group', 'supergroup'], true)) {
            return response()->noContent();
        }

        return $next($request);
    }
}
