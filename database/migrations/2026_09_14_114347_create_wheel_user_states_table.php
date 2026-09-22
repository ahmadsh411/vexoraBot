<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wheel_user_states', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('wheel_id')
                ->constrained('wheels')
                ->cascadeOnDelete();

            // ============================================================
            //  اللفات
            // ============================================================
            $table->unsignedInteger('available_spins')->default(0);
            $table->unsignedInteger('total_spins_used')->default(0);
            $table->decimal('total_won_amount', 18, 2)->default(0);

            // ============================================================
            //  اليومي
            // ============================================================
            $table->unsignedInteger('spins_today')->default(0);
            $table->date('last_spin_date')->nullable();

            $table->timestamps();

            // ============================================================
            //  Indexes
            // ============================================================
            $table->unique(['user_id', 'wheel_id'], 'wheel_user_unique');
            $table->index(['user_id', 'available_spins'], 'wus_user_spins');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wheel_user_states');
    }
};
