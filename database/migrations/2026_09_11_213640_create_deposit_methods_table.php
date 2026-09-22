<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_methods', function (Blueprint $table) {
            $table->id();

            // ============================================================
            //  التعريف
            // ============================================================
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->string('icon', 10)->default('💰');
            $table->string('currency', 3)->default('SYP');

            // ============================================================
            //  نوع البوابة
            // ============================================================
            $table->enum('gateway_type', ['syriatel', 'shamcash', 'manual'])
                ->default('manual')
                ->comment('نوع البوابة للتحقق التلقائي');

            // ============================================================
            //  بيانات الحساب (للعرض)
            // ============================================================
            $table->string('account_number', 100)->nullable();
            $table->string('account_name', 100)->nullable();

            // ============================================================
            //  بيانات التحقق التلقائي
            // ============================================================
            $table->string('receiver_gsm', 20)->nullable()
                ->comment('رقم GSM المستقبل (سيرياتيل)');

            $table->string('receiver_address', 100)->nullable()
                ->comment('عنوان المحفظة (شام كاش)');

            // ============================================================
            //  التعليمات والتفاصيل
            // ============================================================
            $table->text('instructions')->nullable();
            $table->json('details')->nullable();

            // ============================================================
            //  الحدود
            // ============================================================
            $table->decimal('min_amount', 18, 2)->default(0);
            $table->decimal('max_amount', 18, 2)->default(0);
            $table->decimal('commission_percent', 5, 2)->default(0);

            // ============================================================
            //  الحالة
            // ============================================================
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('auto_verify')->default(false)
                ->comment('تفعيل التحقق التلقائي');

            $table->integer('sort_order')->default(0)->index();

            $table->timestamps();

            // ============================================================
            //  Indexes
            // ============================================================
            $table->index(['is_active', 'sort_order'], 'dm_active_sort');
            $table->index('gateway_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_methods');
    }
};
