<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════
        //  1) gem_balances — رصيد الجواهر لكل مستخدم
        // ═══════════════════════════════════════════════════════
        Schema::create('gem_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('balance')->default(0);
            $table->integer('total_earned')->default(0);
            $table->integer('total_spent')->default(0);
            $table->timestamps();

            $table->index('balance');
        });

        // ═══════════════════════════════════════════════════════
        //  2) gem_transactions — سجل كل حركات الجواهر
        // ═══════════════════════════════════════════════════════
        Schema::create('gem_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');  // موجب = اكتساب، سالب = استهلاك
            $table->string('type', 20);   // earn | spend | refund
            $table->string('source', 30); // deposit | exchange | wheel
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('type');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gem_transactions');
        Schema::dropIfExists('gem_balances');
    }
};
