<?php

use App\Http\Controllers\WheelApiController;
use Illuminate\Support\Facades\Route;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Webhook;

// 1. مسارات العجلة الخاصة بك
Route::prefix('wheel')->group(function () {
    Route::post('/state', [WheelApiController::class, 'state']);
    Route::post('/spin', [WheelApiController::class, 'spin']);
});

// 2. مسار تفعيل الـ Webhook
Route::get('/setup-webhook', function (Nutgram $bot) {
    $bot->deleteWebhook();
    $url = 'https://vexora-backend-0j8z.onrender.com/api/telegram/webhook';
    $bot->setWebhook($url);

    return response()->json([
        'status' => 'success',
        'message' => 'تم تفعيل الـ Webhook بنجاح!',
        'url' => $url
    ]);
});

// 3. مسار استقبال الرسائل مخصص كـ Webhook صراحةً
Route::post('/telegram/webhook', function (Nutgram $bot) {
    $bot->setRunningMode(Webhook::class);
    $bot->run();
});
