<?php

use Illuminate\Support\Facades\Route;


// ============================================================
//  🎡 Wheel WebApp
// ============================================================
// ✅ Wheel WebApp API
use App\Http\Controllers\WheelApiController;



// ✅ Wheel WebApp Page
Route::get('/wheel', function () {
    return file_get_contents(public_path('wheel/index.html'));
});
