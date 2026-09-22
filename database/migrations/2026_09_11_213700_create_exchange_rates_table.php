<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();

            // ============================================================
            //  الزوج
            // ============================================================
            $table->string('from_currency', 3)->comment('SYP أو USD');
            $table->string('to_currency', 3)->comment('SYP أو USD');

            // ============================================================
            //  السعر
            // ============================================================
            $table->decimal('rate', 18, 6)
                ->comment('سعر الصرف');

            $table->decimal('buy_rate', 18, 6)->nullable()
                ->comment('سعر الشراء (اختياري)');

            $table->decimal('sell_rate', 18, 6)->nullable()
                ->comment('سعر البيع (اختياري)');

            // ============================================================
            //  العمولة
            // ============================================================
            $table->decimal('commission_percent', 5, 2)
                ->default(0)
                ->comment('نسبة العمولة %');

            // ============================================================
            //  الحالة
            // ============================================================
            $table->boolean('is_active')->default(true)->index();

            // ============================================================
            //  المراجع
            // ============================================================
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            // ============================================================
            //  التوقيتات
            // ============================================================
            $table->timestamps();

            // ============================================================
            //  Indexes
            // ============================================================
            $table->index(
                ['from_currency', 'to_currency', 'is_active'],
                'exchange_rates_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
