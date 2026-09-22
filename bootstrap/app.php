<?php

use App\Telegram\Middleware\TelegramOnlyPrivate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',      // ✅ أضف هذا
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ✅ ثق بكل الـ proxies (Cloudflare)
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            TelegramOnlyPrivate::class,
        ]);

        // ✅ ثق بكل الـ hosts (trycloudflare.com)


        // ✅ استثناء API من CSRF
        $middleware->validateCsrfTokens(except: [
            'api/wheel/*',
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->withProviders([
        App\Providers\TelegramServiceProvider::class,
    ])
    ->create();
