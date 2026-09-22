<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();

            // ============================================================
            //  الأطراف
            // ============================================================
            $table->foreignId('referrer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('referred_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // ============================================================
            //  المستوى والنوع
            // ============================================================
            $table->tinyInteger('level')->default(1)->comment('1 أو 2');

            $table->enum('type', ['instant', 'cycle'])->default('instant');

            // ============================================================
            //  الحالة
            // ============================================================
            $table->enum('status', ['active', 'inactive', 'blocked'])
                ->default('active')->index();

            // ============================================================
            //  الإحصائيات
            // ============================================================
            $table->decimal('total_deposited', 18, 2)->default(0);
            $table->decimal('total_withdrawn', 18, 2)->default(0);
            $table->decimal('total_burned', 18, 2)->default(0);
            $table->decimal('total_earned', 18, 2)->default(0);

            $table->unsignedInteger('deposits_count')->default(0);
            $table->unsignedInteger('withdrawals_count')->default(0);

            $table->timestamps();

            // ============================================================
            //  Indexes
            // ============================================================
            $table->unique(
                ['referrer_id', 'referred_id', 'level'],
                'ref_unique'
            );
            $table->index('referrer_id');
            $table->index('referred_id');
            $table->index(['type', 'status'], 'ref_type_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
