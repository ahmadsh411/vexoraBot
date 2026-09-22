<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_codes', function (Blueprint $table) {
            $table->id();

            // ============================================================
            //  الكود
            // ============================================================
            $table->string('code', 32)->unique()
                ->comment('الكود الفريد مثل GIFT_A3F9B2C1');

            // ============================================================
            //  القيمة والعملة
            // ============================================================
            $table->decimal('value', 18, 2)
                ->comment('قيمة الهدية');

            $table->enum('currency', ['NSP', 'USD'])
                ->default('NSP')
                ->comment('العملة NSP ل.س أو USD');

            // ============================================================
            //  الاستخدام
            // ============================================================
            $table->unsignedInteger('max_uses')->default(1)
                ->comment('الحد الأقصى للمستخدمين (1 = أول مستخدم فقط)');

            $table->unsignedInteger('used_count')->default(0);

            $table->enum('status', ['active', 'used', 'expired', 'disabled'])
                ->default('active')
                ->comment('حالة الكود');

            // ============================================================
            //  الأدمن المنشئ
            // ============================================================
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('الأدمن الذي أنشأ الكود');

            // ============================================================
            //  القناة (للنشر والتعديل لاحقاً)
            // ============================================================
            $table->bigInteger('channel_id')->nullable()
                ->comment('معرّف قناة النشر (قيمة سالبة)');

            $table->bigInteger('channel_msg_id')->nullable()
                ->comment('معرّف رسالة القناة لتعديلها بعد الاستخدام');

            // ============================================================
            //  الصلاحية والملاحظات
            // ============================================================
            $table->timestamp('expires_at')->nullable()
                ->comment('تاريخ انتهاء الصلاحية');

            $table->text('note')->nullable()
                ->comment('ملاحظة داخلية للأدمن');

            $table->timestamps();

            // ============================================================
            //  Indexes
            // ============================================================
            // ✅ لا نضيف ->index() على enum status لأننا نعرّف الفهرس هنا
            $table->index('status', 'gc_status_idx');
            $table->index(['status', 'expires_at'], 'gc_status_expires_idx');
            $table->index('created_by', 'gc_created_by_idx');
            $table->index('currency', 'gc_currency_idx');
            // ملاحظة: code له unique مسبقاً، لا حاجة لفهرس إضافي عليه
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_codes');
    }
};
