<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_payment_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('deposit_method_id')
                ->constrained('deposit_methods')
                ->cascadeOnDelete();

            // ============================================================
            //  الحساب
            // ============================================================
            $table->string('account_number', 100);
            $table->string('account_name', 100)->nullable();

            // ============================================================
            //  الإحصائيات
            // ============================================================
            $table->decimal('total_deposited', 18, 2)->default(0);
            $table->decimal('total_withdrawn', 18, 2)->default(0);
            $table->decimal('available_balance', 18, 2)->default(0);

            $table->unsignedInteger('deposits_count')->default(0);
            $table->unsignedInteger('withdrawals_count')->default(0);

            // ============================================================
            //  الحالة
            // ============================================================
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            // ============================================================
            //  Indexes
            // ============================================================
            $table->unique(
                ['user_id', 'deposit_method_id', 'account_number'],
                'user_payment_accounts_unique'
            );
            $table->index(['user_id', 'is_active'], 'upa_user_active');
            $table->index('deposit_method_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_payment_accounts');
    }
};
