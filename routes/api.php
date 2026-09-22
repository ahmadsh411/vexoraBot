<?php

use App\Http\Controllers\WheelApiController;
use Illuminate\Support\Facades\Route;
use SergiX44\Nutgram\Nutgram;

// 1. مسارات العجلة الخاصة بك
Route::prefix('wheel')->group(function () {
    Route::post('/state', [WheelApiController::class, 'state']);
    Route::post('/spin', [WheelApiController::class, 'spin']);
});

// 2. مسار تفعيل الـ Webhook (الذي شغلته قبل قليل)
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

// 3. المسار المفقود: معالجة رسائل تلغرام القادمة (مهم جداً!)
Route::post('/telegram/webhook', function (Nutgram $bot) {
    $bot->run();
});
