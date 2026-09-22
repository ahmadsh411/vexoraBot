<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_audits', function (Blueprint $table) {
            $table->id();

            // ============================================================
            //  المرجع
            // ============================================================
            $table->foreignId('transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->nullOnDelete();

            // ============================================================
            //  الفاعل
            // ============================================================
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('المستخدم صاحب العملية');

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('من قام بالإجراء (أدمن/نظام)');

            $table->string('actor_type', 50)->default('system')
                ->comment('system, admin, user, api, scheduler');

            // ============================================================
            //  الحدث
            // ============================================================
            $table->string('event', 100)->index()
                ->comment('created, approved, rejected, completed, failed, refunded, ...');

            // ============================================================
            //  التغييرات
            // ============================================================
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();

            $table->decimal('amount_before', 18, 2)->nullable();
            $table->decimal('amount_after', 18, 2)->nullable();

            $table->decimal('balance_before', 18, 2)->nullable();
            $table->decimal('balance_after', 18, 2)->nullable();

            $table->string('currency', 3)->nullable();

            // ============================================================
            //  البيانات الإضافية
            // ============================================================
            $table->json('payload')->nullable();
            $table->text('note')->nullable();

            // ============================================================
            //  معلومات الطلب
            // ============================================================
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_id', 100)->nullable()->index();

            $table->timestamp('created_at')->useCurrent();

            // ============================================================
            //  Indexes
            // ============================================================
            $table->index(['transaction_id', 'event'], 'audit_tx_event');
            $table->index(['user_id', 'created_at'], 'audit_user_created');
            $table->index(['actor_id', 'created_at'], 'audit_actor_created');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_audits');
    }
};
