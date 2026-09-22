<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            // ============================================================
            //  النوع والمالك
            // ============================================================
            $table->enum('type', ['main', 'user', 'agent'])
                ->default('user')
                ->comment('نوع المحفظة');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('مالك المحفظة (null للمحفظة الرئيسية)');

            // ============================================================
            //  الأرصدة
            // ============================================================
            $table->decimal('balance_nsp', 18, 2)
                ->default(0)
                ->comment('الرصيد بالليرة السورية');

            $table->decimal('balance_usd', 18, 2)
                ->default(0)
                ->comment('الرصيد بالدولار الأمريكي');

            // ============================================================
            //  القفل (Pessimistic Locking)
            // ============================================================
            $table->boolean('is_locked')->default(false)
                ->comment('قفل المحفظة (منع العمليات المتزامنة)');

            $table->timestamp('locked_at')->nullable();

            // ============================================================
            //  إحصائيات
            // ============================================================
            $table->decimal('total_deposit_nsp', 18, 2)->default(0);
            $table->decimal('total_deposit_usd', 18, 2)->default(0);
            $table->decimal('total_withdraw_nsp', 18, 2)->default(0);
            $table->decimal('total_withdraw_usd', 18, 2)->default(0);

            $table->decimal('total_commission_nsp', 18, 2)->default(0);
            $table->decimal('total_commission_usd', 18, 2)->default(0);

            // ============================================================
            //  الحالة
            // ============================================================
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_frozen')->default(false)->index();

            $table->timestamp('last_synced_at')->nullable();

            // ============================================================
            //  التوقيتات
            // ============================================================
            $table->timestamps();
            $table->softDeletes();

            // ============================================================
            //  Indexes
            // ============================================================
            $table->unique(['user_id', 'type'], 'wallets_user_type_unique');
            $table->index(['type', 'is_active'], 'wallets_type_active_idx');
            $table->index(['is_frozen', 'is_active'], 'wallets_frozen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
