<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_settings', function (Blueprint $table) {
            $table->id();

            // ============================================================
            //  النسب الفورية
            // ============================================================
            $table->decimal('instant_level_1_percent', 5, 2)->default(5.00);
            $table->decimal('instant_level_2_percent', 5, 2)->default(2.00);

            // ============================================================
            //  النسب الدورية
            // ============================================================
            $table->decimal('cycle_level_1_percent', 5, 2)->default(10.00);
            $table->decimal('cycle_level_2_percent', 5, 2)->default(3.00);

            // ============================================================
            //  مدة الدورة
            // ============================================================
            $table->unsignedInteger('cycle_days')->default(10);

            // ============================================================
            //  الحدود
            // ============================================================
            $table->decimal('min_instant_reward', 18, 2)->default(0);
            $table->decimal('max_instant_reward', 18, 2)->default(0);

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

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_settings');
    }
};
