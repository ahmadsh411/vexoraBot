<?php

use App\Http\Controllers\WheelApiController;
use Illuminate\Support\Facades\Route;
use SergiX44\Nutgram\Nutgram;

Route::prefix('wheel')->group(function () {
    Route::post('/state', [WheelApiController::class, 'state']);
    Route::post('/spin', [WheelApiController::class, 'spin']);
});




/*
|--------------------------------------------------------------------------
| Telegram Bot Webhook Route
|--------------------------------------------------------------------------
*/
Route::post('/telegram/webhook', function (Nutgram $bot) {
    
    // أمر /start
    $bot->onCommand('start', function (Nutgram $bot) {
        $bot->sendMessage('أهلاً بك في بوت VEXORA! البوت يعمل الآن بنجاح 🎉');
    });

    // معالجة باقي الطلبات
    $bot->run();
});
