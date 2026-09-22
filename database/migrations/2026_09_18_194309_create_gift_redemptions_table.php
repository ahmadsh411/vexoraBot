<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_redemptions', function (Blueprint $table) {
            $table->id();

            // ============================================================
            //  ربط بالكود والمستخدم
            // ============================================================
            $table->foreignId('gift_code_id')
                ->constrained('gift_codes')
                ->cascadeOnDelete()
                ->comment('الكود المُستبدل');

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('من استخدم الكود');

            // ============================================================
            //  نسخة من القيمة والعملة وقت الاستبدال
            // ============================================================
            $table->decimal('value', 18, 2)
                ->comment('القيمة وقت الاستبدال');

            $table->enum('currency', ['NSP', 'USD'])
                ->comment('العملة وقت الاستبدال');

            // ============================================================
            //  ربط بعملية المحفظة (Transaction) لأغراض التدقيق
            // ============================================================
            $table->foreignId('transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->nullOnDelete()
                ->comment('العملية المالية المرتبطة');

            $table->timestamp('redeemed_at')->useCurrent()
                ->comment('وقت الاستبدال');

            // ============================================================
            //  Indexes
            // ============================================================
            $table->unique(['gift_code_id', 'user_id'], 'gr_code_user_unique');
            $table->index('user_id', 'gr_user_idx');
            $table->index('redeemed_at', 'gr_redeemed_idx');
            $table->index('transaction_id', 'gr_transaction_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_redemptions');
    }
};
