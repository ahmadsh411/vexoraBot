<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);

        // ✅ تشغيل الـ Seeder تلقائياً في السيرفر البعيد إذا كانت قاعدة البيانات فارغة
        $this->autoRunSeeder();
    }

    /**
     * تشغيل الـ Seeder مرة واحدة تلقائياً عند الحاجة
     */
    private function autoRunSeeder(): void
    {
        try {
            // يفحص إذا كان جدول الإعدادات موجوداً وفارغاً
            if (Schema::hasTable('settings') && Setting::count() === 0) {
                Artisan::call('db:seed', ['--force' => true]);
                Log::info('Auto-Seeder: Database seeded successfully on remote server.');
            }
        } catch (\Throwable $e) {
            Log::warning('Auto-Seeder failed: ' . $e->getMessage());
        }
    }
}
