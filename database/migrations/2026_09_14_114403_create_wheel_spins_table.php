<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wheel_spins', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('wheel_id')
                ->constrained('wheels')
                ->cascadeOnDelete();

            $table->foreignId('prize_id')
                ->nullable()
                ->constrained('wheel_prizes')
                ->nullOnDelete();

            // ============================================================
            //  النتيجة
            // ============================================================
            $table->decimal('won_value', 18, 2)->default(0);
            $table->string('currency', 3)->default('SYP');

            // ============================================================
            //  المصدر
            // ============================================================
            $table->enum('source', [
                'deposit_syp',
                'deposit_usd',
                'referral',
                'admin',
                'manual',
            ])->default('deposit_syp')->index();

            $table->json('metadata')->nullable();

            $table->timestamps();

            // ============================================================
            //  Indexes
            // ============================================================
            $table->index(['user_id', 'created_at'], 'ws_user_created');
            $table->index(['wheel_id', 'created_at'], 'ws_wheel_created');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wheel_spins');
    }
};
