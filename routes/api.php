<?php

use App\Http\Controllers\WheelApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('wheel')->group(function () {
    Route::post('/state', [WheelApiController::class, 'state']);
    Route::post('/spin', [WheelApiController::class, 'spin']);
});
