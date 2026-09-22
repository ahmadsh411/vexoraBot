<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use App\Services\PasswordEncryptionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OwnerSeeder extends Seeder
{
    /**
     * ✅ بيانات المالك الأساسية.
     */
    private const OWNER_TELEGRAM_ID = 7453447854;

    /**
     * ⚠️ عدّل هذه البيانات قبل التشغيل.
     */
    private const OWNER_TELEGRAM_USERNAME = 'VexoraOwner';   // ← اسمك في Telegram (بدون @)
    private const OWNER_USERNAME          = 'Vexora_Owner';  // ← اسم الدخول في البوت
    private const OWNER_PASSWORD          = 'Owner@2026#VEXORA'; // ← كلمة مرور قوية
    private const OWNER_FIRST_NAME        = 'Owner';
    private const OWNER_LAST_NAME         = 'VEXORA';

    public function run(): void
    {
        $this->command->info('👑 إنشاء حساب مالك البوت...');

        // ✅ 1. تحقق من وجود المالك
        $existing = User::withTrashed()
            ->where('telegram_id', self::OWNER_TELEGRAM_ID)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
                $this->command->warn('♻️ تم استعادة حساب المالك المحذوف.');
            } else {
                $this->command->warn('⚠️ حساب المالك موجود مسبقاً.');
            }

            $this->ensureOwnerPrivileges($existing);
            return;
        }

        // ✅ 2. تحقق من عدم استخدام username
        if (User::withTrashed()->where('username', self::OWNER_USERNAME)->exists()) {
            $this->command->error(
                '❌ اسم المستخدم "' . self::OWNER_USERNAME . '" محجوز. غيّره في OwnerSeeder.'
            );
            return;
        }

        // ✅ 3. تشفير كلمة المرور
        $encryptionService = app(PasswordEncryptionService::class);
        $encrypted = $encryptionService->encrypt(self::OWNER_PASSWORD);

        // ✅ 4. إنشاء المالك
        $owner = User::create([
            'telegram_id'         => self::OWNER_TELEGRAM_ID,
            'telegram_username'   => self::OWNER_TELEGRAM_USERNAME,
            'username'            => self::OWNER_USERNAME,
            'password_encrypted'  => $encrypted['encrypted'],
            'password_key_id'     => $encrypted['key_id'],
            'password_changed_at' => now(),
            'first_name'          => self::OWNER_FIRST_NAME,
            'last_name'           => self::OWNER_LAST_NAME,
            'is_active'           => true,
            'is_admin'            => true,
            'is_super_admin'      => true,
            'referral_code'       => $this->generateUniqueReferralCode(),
            'referrals_count'     => 0,
            'referral_earnings'   => 0,
        ]);

        // ✅ 5. إنشاء المحفظة
        $this->createWallet($owner);

        // ✅ 6. عرض البيانات
        $this->displayOwnerInfo($owner);
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function ensureOwnerPrivileges(User $user): void
    {
        $updated = false;

        if (! $user->is_active) {
            $user->is_active = true;
            $updated = true;
        }

        if (! $user->is_admin) {
            $user->is_admin = true;
            $updated = true;
        }

        if (! $user->is_super_admin) {
            $user->is_super_admin = true;
            $updated = true;
        }

        if ($updated) {
            $user->save();
            $this->command->info('✅ تم تحديث صلاحيات المالك.');
        }

        $this->createWallet($user);
        $this->displayOwnerInfo($user);
    }

    private function createWallet(User $user): void
    {
        $wallet = Wallet::firstOrCreate(
            [
                'user_id' => $user->id,
                'type'    => Wallet::TYPE_USER,
            ],
            [
                'balance_nsp'        => 0,
                'balance_usd'        => 0,
                'total_deposit_nsp'  => 0,
                'total_deposit_usd'  => 0,
                'total_withdraw_nsp' => 0,
                'total_withdraw_usd' => 0,
                'is_active'          => true,
                'is_frozen'          => false,
            ],
        );

        if ($wallet->wasRecentlyCreated) {
            $this->command->info('✅ تم إنشاء محفظة المالك.');
        }
    }

    private function generateUniqueReferralCode(): string
    {
        do {
            $code = 'VX' . strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    private function displayOwnerInfo(User $owner): void
    {
        $this->command->newLine();
        $this->command->info('╔══════════════════════════════════════════╗');
        $this->command->info('║      👑 حساب مالك البوت جاهز            ║');
        $this->command->info('╚══════════════════════════════════════════╝');
        $this->command->newLine();

        $this->command->table(
            ['الحقل', 'القيمة'],
            [
                ['🆔 معرف المستخدم',    '#' . $owner->id],
                ['📱 Telegram ID',      $owner->telegram_id],
                ['📛 اسم المستخدم',      $owner->username],
                ['🔑 كلمة المرور',       self::OWNER_PASSWORD],
                ['👤 الاسم الكامل',      $owner->full_name],
                ['👑 الدور',            'مشرف أساسي'],
                ['🔗 رابط الإحالة',      $owner->referral_link],
                ['✅ الحالة',           'نشط'],
            ],
        );

        $this->command->newLine();
        $this->command->warn('⚠️ احتفظ ببيانات الدخول في مكان آمن!');
        $this->command->info('💡 استخدم /start في البوت للدخول.');
        $this->command->newLine();
    }
}
