<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();

            // ============================================================
            //  الأطراف
            // ============================================================
            $table->foreignId('referrer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('referred_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->nullOnDelete();

            // ============================================================
            //  النوع
            // ============================================================
            $table->tinyInteger('level')->default(1);

            $table->enum('type', ['instant', 'cycle'])->default('instant');

            $table->enum('basis', ['deposit', 'burn'])->default('deposit');

            // ============================================================
            //  المبلغ
            // ============================================================
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->default('SYP');
            $table->decimal('commission_percent', 5, 2);

            // ============================================================
            //  الحالة
            // ============================================================
            $table->enum('status', ['pending', 'paid', 'cancelled'])
                ->default('paid')->index();

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            // ============================================================
            //  Indexes
            // ============================================================
            $table->index('referrer_id');
            $table->index('referred_id');
            $table->index('transaction_id');
            $table->index(['type', 'basis'], 'rr_type_basis');
            $table->index(['status', 'created_at'], 'rr_status_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
    }
};
