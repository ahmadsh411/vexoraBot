<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_cycle_rewards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cycle_id')
                ->constrained('referral_cycles')
                ->cascadeOnDelete();

            $table->foreignId('referrer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // ============================================================
            //  الإحصائيات
            // ============================================================
            $table->decimal('total_burned_l1', 18, 2)->default(0);
            $table->decimal('total_burned_l2', 18, 2)->default(0);

            $table->decimal('reward_l1', 18, 2)->default(0);
            $table->decimal('reward_l2', 18, 2)->default(0);
            $table->decimal('total_reward', 18, 2)->default(0);

            $table->string('currency', 3)->default('SYP');

            $table->decimal('percent_l1', 5, 2)->default(0);
            $table->decimal('percent_l2', 5, 2)->default(0);

            $table->unsignedInteger('referred_count_l1')->default(0);
            $table->unsignedInteger('referred_count_l2')->default(0);

            // ============================================================
            //  الحالة
            // ============================================================
            $table->enum('status', ['pending', 'paid', 'cancelled'])
                ->default('pending')->index();

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            // ============================================================
            //  Indexes
            // ============================================================
            $table->unique(['cycle_id', 'referrer_id'], 'cycle_referrer_unique');
            $table->index('referrer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_cycle_rewards');
    }
};
