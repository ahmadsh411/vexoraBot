<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wheel_prizes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wheel_id')
                ->constrained('wheels')
                ->cascadeOnDelete();

            // ============================================================
            //  التعريف
            // ============================================================
            $table->string('name', 100);
            $table->string('icon', 10)->default('🎁');
            $table->string('color', 20)->default('#4CAF50');

            // ============================================================
            //  القيمة
            // ============================================================
            $table->decimal('value', 18, 2)->default(0);
            $table->string('currency', 3)->default('SYP');

            $table->enum('type', ['balance', 'empty', 'recycle'])
                ->default('balance')->index();

            // ============================================================
            //  الوزن (النسبة)
            // ============================================================
            $table->unsignedInteger('weight')->default(10)
                ->comment('النسبة من 0 إلى 100');

            // ============================================================
            //  الترتيب والحالة
            // ============================================================
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            // ============================================================
            //  Indexes
            // ============================================================
            $table->index(['wheel_id', 'is_active'], 'wp_wheel_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wheel_prizes');
    }
};
