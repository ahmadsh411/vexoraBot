<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // ============================================================
            //  المرجع الفريد
            // ============================================================
            $table->string('reference', 50)->unique();

            // ============================================================
            //  الأطراف
            // ============================================================
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('from_wallet_id')
                ->nullable()
                ->constrained('wallets')
                ->nullOnDelete();

            $table->foreignId('to_wallet_id')
                ->nullable()
                ->constrained('wallets')
                ->nullOnDelete();

            // ============================================================
            //  حساب الدفع
            // ============================================================
            $table->foreignId('user_payment_account_id')
                ->nullable()
                ->constrained('user_payment_accounts')
                ->nullOnDelete();

            $table->string('user_account_number', 100)->nullable();

            // ============================================================
            //  النوع
            // ============================================================
            $table->enum('type', [
                'deposit',
                'withdraw',
                'deposit_usd',
                'withdraw_usd',
                'exchange',
                'ichancy_deposit',
                'ichancy_withdraw',
                'commission',
                'refund',
                'admin_credit',
                'admin_debit',
            ])->index();

            // ============================================================
            //  العملات والمبالغ
            // ============================================================
            $table->string('from_currency', 3)->nullable();
            $table->string('to_currency', 3)->nullable();

            $table->decimal('amount_from', 18, 2)->default(0);
            $table->decimal('amount_to', 18, 2)->default(0);
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->decimal('commission_amount', 18, 2)->default(0);

            // ============================================================
            //  الحالة
            // ============================================================
            $table->enum('status', [
                'pending',
                'approved',
                'completed',
                'rejected',
                'cancelled',
                'failed',
            ])->default('pending')->index();

            // ============================================================
            //  المراجع الخارجية
            // ============================================================
            $table->string('ichancy_transaction_id')->nullable()->index();
            $table->string('external_reference')->nullable();

            // ============================================================
            //  الأدمن المنفّذ
            // ============================================================
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // ============================================================
            //  ملاحظات وإثباتات
            // ============================================================
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->string('proof_file')->nullable();

            // ============================================================
            //  التوقيتات
            // ============================================================
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ============================================================
            //  Indexes
            // ============================================================
            $table->index(['user_id', 'type', 'status'], 'tx_user_type_status');
            $table->index(['status', 'created_at'], 'tx_status_created');
            $table->index(['type', 'created_at'], 'tx_type_created');
            $table->index(['completed_at', 'status'], 'tx_completed_status');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
