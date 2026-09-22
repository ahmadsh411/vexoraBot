<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_cycles', function (Blueprint $table) {
            $table->id();

            // ============================================================
            //  التواريخ
            // ============================================================
            $table->date('start_date');
            $table->date('end_date');

            // ============================================================
            //  الحالة
            // ============================================================
            $table->enum('status', ['open', 'processing', 'closed', 'cancelled'])
                ->default('open')->index();

            // ============================================================
            //  الإحصائيات
            // ============================================================
            $table->decimal('total_burned', 18, 2)->default(0);
            $table->decimal('total_rewards', 18, 2)->default(0);
            $table->unsignedInteger('referrers_count')->default(0);
            $table->unsignedInteger('referred_count')->default(0);

            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            // ============================================================
            //  Indexes
            // ============================================================
            $table->unique(['start_date', 'end_date'], 'cycle_dates_unique');
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_cycles');
    }
};
