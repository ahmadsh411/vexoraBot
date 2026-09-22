<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // ============================================================
            //  Telegram
            // ============================================================
            $table->unsignedBigInteger('telegram_id')
                ->unique()
                ->comment('معرّف Telegram الفريد');

            $table->string('telegram_username', 64)
                ->nullable()
                ->index()
                ->comment('اسم المستخدم في Telegram');

            // ============================================================
            //  حساب المنصة
            // ============================================================
            $table->string('username', 64)
                ->unique()
                ->comment('اسم المستخدم في المنصة');

            $table->text('password_encrypted')
                ->comment('كلمة المرور مشفرة بمفتاح منفصل');

            $table->string('password_key_id', 32)
                ->default('v1')
                ->comment('معرّف مفتاح التشفير (للتناوب)');

            $table->string('ichancy_player_id', 64)
                ->unique()
                ->nullable()
                ->comment('معرّف اللاعب في إيشانسي');

            // ============================================================
            //  البيانات الشخصية
            // ============================================================
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();

            // ============================================================
            //  الصلاحيات
            // ============================================================
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_admin')->default(false)->index();
            $table->boolean('is_super_admin')->default(false)->index();

            // ============================================================
            //  التوقيتات
            // ============================================================
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('admin_seen_at')->nullable()->index();
            $table->timestamp('password_changed_at')->nullable();

            // ============================================================
            //  الإحالات (denormalized - للأداء)
            // ============================================================
            $table->string('referral_code', 20)
                ->unique()
                ->nullable()
                ->comment('كود الإحالة الفريد');

            $table->enum('referral_type', ['instant', 'cycle'])
                ->nullable()
                ->comment('نوع الإحالة الذي اختاره');

            $table->timestamp('referral_chosen_at')->nullable();

            $table->foreignId('referred_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('المُحيل المباشر (L1)');

            $table->foreignId('referred_by_level_2')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('المُحيل من المستوى 2');

            $table->unsignedInteger('referrals_count')->default(0);
            $table->decimal('referral_earnings', 18, 2)->default(0);

            // ============================================================
            //  التوقيتات العامة
            // ============================================================
            $table->timestamps();
            $table->softDeletes();

            // ============================================================
            //  Indexes مركبة
            // ============================================================
            $table->index(['is_admin', 'is_super_admin'], 'users_admin_idx');
            $table->index(['is_active', 'is_admin'], 'users_active_admin_idx');
            $table->index(['referred_by', 'referral_type'], 'users_referrer_type_idx');
            $table->index('created_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
