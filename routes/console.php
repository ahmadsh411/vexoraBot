<?php

use Illuminate\Support\Facades\Schedule;

// ═══════════════════════════════════════════════════════════
//  Transactions Lifecycle
// ═══════════════════════════════════════════════════════════

Schedule::command('transactions:archive --force')
    ->dailyAt('02:00')
    ->timezone('Asia/Damascus')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('transactions:clean --force')
    ->dailyAt('03:00')
    ->timezone('Asia/Damascus')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// ═══════════════════════════════════════════════════════════
//  Reports
// ═══════════════════════════════════════════════════════════

Schedule::command('reports:full')
    ->dailyAt('00:00')
    ->timezone('Asia/Damascus')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// ═══════════════════════════════════════════════════════════
//  Referral Cycles
// ═══════════════════════════════════════════════════════════

Schedule::command('referral:process-cycle')
    ->dailyAt('04:00')
    ->timezone('Asia/Damascus')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// ═══════════════════════════════════════════════════════════
//  Wheel Daily Reset
// ═══════════════════════════════════════════════════════════

Schedule::command('wheel:reset-daily')
    ->dailyAt('00:05')
    ->timezone('Asia/Damascus')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// ═══════════════════════════════════════════════════════════
//  Logs Cleanup
// ═══════════════════════════════════════════════════════════

Schedule::command('logs:clean --days=90 --force')
    ->weeklyOn(0, '05:00')
    ->timezone('Asia/Damascus')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// ═══════════════════════════════════════════════════════════
//  Backup
// ═══════════════════════════════════════════════════════════

Schedule::command('backup:run --keep=7')
    ->dailyAt('01:00')
    ->timezone('Asia/Damascus')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// ═══════════════════════════════════════════════════════════
//  Balance Check + Sync  ← محدّث
// ═══════════════════════════════════════════════════════════

// ✅ مزامنة المحفظة الرئيسية — كل ساعة
Schedule::command('wallet:sync --force')
    ->hourly()
    ->timezone('Asia/Damascus')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// ✅ فحص تناسق الأرصدة — أسبوعياً
Schedule::command('balance:check')
    ->weeklyOn(0, '06:00')
    ->timezone('Asia/Damascus')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// ═══════════════════════════════════════════════════════════
//  Pending Withdraws Notification
// ═══════════════════════════════════════════════════════════

Schedule::command('notify:pending --hours=6')
    ->everySixHours()
    ->timezone('Asia/Damascus')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();



Schedule::command('ichancy:monitor-balance --threshold=200000 --cooldown=360')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
