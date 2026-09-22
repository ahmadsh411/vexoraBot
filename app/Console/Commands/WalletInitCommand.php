<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ExchangeRateService;
use App\Services\WalletService;
use Illuminate\Console\Command;

class WalletInitCommand extends Command
{
    protected $signature = 'wallet:init
                            {--force : إعادة إنشاء المحفظة الرئيسية}
                            {--rates : إضافة أسعار صرف افتراضية}
                            {--users : إنشاء محفظة لكل مستخدم موجود}
                            {--sync : مزامنة المحفظة الرئيسية فوراً}';

    protected $description = 'تهيئة نظام المحافظ + مزامنة الرئيسية';

    public function handle(
        WalletService $walletService,
        ExchangeRateService $exchangeRateService,
    ): int {
        $this->info('🚀 بدء تهيئة نظام المحافظ...');
        $this->newLine();

        // ─── 1. المحفظة الرئيسية ───
        $this->createMainWallet($walletService);

        // ─── 2. أسعار الصرف ───
        if ($this->option('rates')) {
            $this->createDefaultRates($exchangeRateService);
        }

        // ─── 3. محافظ المستخدمين ───
        if ($this->option('users')) {
            $this->createUserWallets($walletService);
        }

        // ─── 4. ✨ المزامنة (جديد) ───
        if ($this->option('sync') || true) {  // ← دائماً مُفعّل
            $this->syncMainWallet();
        }

        $this->newLine();
        $this->info('✅ اكتملت التهيئة بنجاح.');

        return self::SUCCESS;
    }

    // ============================================================
    //  🏦 إنشاء المحفظة الرئيسية
    // ============================================================
    private function createMainWallet(WalletService $walletService): void
    {
        $this->info('🏦 إنشاء المحفظة الرئيسية...');

        $existing = Wallet::where('type', Wallet::TYPE_MAIN)->first();

        if ($existing && ! $this->option('force')) {
            $this->warn("⚠️  موجودة مسبقاً (ID: {$existing->id})");
            return;
        }

        if ($existing && $this->option('force')) {
            $existing->forceDelete();
        }

        $wallet = $walletService->getMainWallet();
        $this->info("✅ تم إنشاء المحفظة الرئيسية (ID: {$wallet->id})");
    }

    // ============================================================
    //  💱 أسعار الصرف
    // ============================================================
    private function createDefaultRates(ExchangeRateService $exchangeRateService): void
    {
        $this->newLine();
        $this->info('💱 إضافة أسعار الصرف الافتراضية...');

        $defaultRates = [
            ['from' => Wallet::CURRENCY_NSP, 'to' => Wallet::CURRENCY_USD, 'rate' => 0.000067, 'commission' => 2.0],
            ['from' => Wallet::CURRENCY_USD, 'to' => Wallet::CURRENCY_NSP, 'rate' => 15000, 'commission' => 2.0],
        ];

        foreach ($defaultRates as $rateData) {
            $existing = ExchangeRate::active()
                ->between($rateData['from'], $rateData['to'])
                ->first();

            if ($existing) {
                $this->warn("⚠️  {$rateData['from']} → {$rateData['to']} موجود");
                continue;
            }

            $exchangeRateService->setRate(
                from: $rateData['from'],
                to: $rateData['to'],
                rate: $rateData['rate'],
                commission: $rateData['commission'],
                notes: 'سعر افتراضي',
            );

            $this->info("✅ {$rateData['from']} → {$rateData['to']} @ {$rateData['rate']}");
        }
    }

    // ============================================================
    //  👥 محافظ المستخدمين
    // ============================================================
    private function createUserWallets(WalletService $walletService): void
    {
        $this->newLine();
        $this->info('👥 إنشاء محافظ لكل مستخدم موجود...');

        $users = User::whereDoesntHave('wallets', function ($query) {
            $query->where('type', Wallet::TYPE_USER);
        })->get();

        if ($users->isEmpty()) {
            $this->warn('⚠️  كل المستخدمين لديهم محافظ.');
            return;
        }

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            $walletService->getUserWallet($user);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ تم إنشاء {$users->count()} محفظة.");
    }

    // ============================================================
    //  🔄 مزامنة المحفظة الرئيسية (جديد)
    // ============================================================
    private function syncMainWallet(): void
    {
        $this->newLine();
        $this->info('🔄 مزامنة المحفظة الرئيسية...');

        $main = Wallet::where('type', Wallet::TYPE_MAIN)->first();

        if (! $main) {
            $this->error('❌ لا توجد محفظة رئيسية.');
            return;
        }

        // ─── المجاميع ───
        $userTotalNsp = (float) Wallet::where('type', Wallet::TYPE_USER)->sum('balance_nsp');
        $userTotalUsd = (float) Wallet::where('type', Wallet::TYPE_USER)->sum('balance_usd');

        $mainNsp = (float) $main->balance_nsp;
        $mainUsd = (float) $main->balance_usd;

        // ─── عرض ───
        $this->table(
            ['المؤشر', 'NSP', 'USD'],
            [
                ['🏦 المحفظة الرئيسية', number_format($mainNsp, 2), number_format($mainUsd, 2)],
                ['👥 مجموع المستخدمين', number_format($userTotalNsp, 2), number_format($userTotalUsd, 2)],
            ],
        );

        // ─── تحديث ───
        $main->update([
            'balance_nsp' => $userTotalNsp,
            'balance_usd' => $userTotalUsd,
        ]);

        $this->info("✅ تمت المزامنة:");
        $this->info("   💰 NSP: {$mainNsp} → {$userTotalNsp}");
        $this->info("   💵 USD: {$mainUsd} → {$userTotalUsd}");
    }
}
