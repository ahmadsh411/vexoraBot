<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use SergiX44\Nutgram\Nutgram;

class TelegramServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Nutgram::class, function () {
            return new Nutgram(config('services.telegram.bot_token'));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // ─── أرشفة وتنظيف ───
                \App\Console\Commands\ArchiveOldTransactions::class,
                \App\Console\Commands\CleanOldTransactions::class,
                \App\Console\Commands\CleanLogs::class,

                // ─── تقارير ───
                \App\Console\Commands\DailyFullReport::class,

                // ─── نسخ احتياطي وصيانة ───
                \App\Console\Commands\RunBackup::class,
                \App\Console\Commands\CheckBalances::class,

                // ─── إحالات وعجلة ───
                \App\Console\Commands\ProcessReferralCycle::class,
                \App\Console\Commands\ResetDailyWheelCommand::class,

                // ─── محافظ ───
                \App\Console\Commands\WalletInitCommand::class,
                \App\Console\Commands\SyncWalletBalances::class,  // ← جديد

                // ─── إشعارات ───
                \App\Console\Commands\NotifyPendingWithdraws::class,
                \App\Console\Commands\SetBotCommands::class,

                //رصيد  الكاشيرا
                \App\Console\Commands\MonitorIChancyBalance::class,
            ]);
        }
    }
}
