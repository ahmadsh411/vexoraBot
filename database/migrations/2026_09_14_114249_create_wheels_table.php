<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wheels', function (Blueprint $table) {
            $table->id();

            // ============================================================
            //  التعريف
            // ============================================================
            $table->string('name', 100)->default('عجلة الحظ');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();

            // ============================================================
            //  الإيداع السوري (SYP)
            // ============================================================
            $table->decimal('deposit_syp_threshold', 18, 2)->default(50000);
            $table->decimal('min_deposit_syp_threshold', 18, 2)->default(1000);
            $table->decimal('max_deposit_syp_threshold', 18, 2)->default(1000000);

            // ============================================================
            //  الإيداع الدولار (USD)
            // ============================================================
            $table->decimal('deposit_usd_threshold', 18, 2)->default(1);
            $table->decimal('min_deposit_usd_threshold', 18, 2)->default(0.5);
            $table->decimal('max_deposit_usd_threshold', 18, 2)->default(100);

            // ============================================================
            //  الإحالة
            // ============================================================
            $table->unsignedInteger('referral_threshold')->default(5);
            $table->unsignedInteger('min_referral_threshold')->default(1);
            $table->unsignedInteger('max_referral_threshold')->default(50);

            // ============================================================
            //  الحد اليومي
            // ============================================================
            $table->unsignedInteger('daily_limit')->default(10);
            $table->unsignedInteger('min_daily_limit')->default(1);
            $table->unsignedInteger('max_daily_limit')->default(100);

            // ============================================================
            //  الحماية
            // ============================================================
            $table->unsignedInteger('max_stored_spins')->default(100);
            $table->boolean('auto_grant_on_deposit')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wheels');
    }
};
