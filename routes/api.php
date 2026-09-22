<?php

use App\Http\Controllers\WheelApiController;
use Illuminate\Support\Facades\Route;
use SergiX44\Nutgram\Nutgram;

Route::prefix('wheel')->group(function () {
    Route::post('/state', [WheelApiController::class, 'state']);
    Route::post('/spin', [WheelApiController::class, 'spin']);
});



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
