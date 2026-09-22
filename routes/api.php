<?php

use App\Http\Controllers\WheelApiController;
use Illuminate\Support\Facades\Route;
use SergiX44\Nutgram\Nutgram;

// ═══════════════════════════════════════════════════════════
// Telegram Bot Webhook Route
// ═══════════════════════════════════════════════════════════
Route::post('/telegram/webhook', function (Nutgram $bot) {
    $bot->run();
});

// ═══════════════════════════════════════════════════════════
// Wheel API Routes
// ═══════════════════════════════════════════════════════════
Route::prefix('wheel')->group(function () {
    Route::post('/state', [WheelApiController::class, 'state']);
    Route::post('/spin', [WheelApiController::class, 'spin']);
});
