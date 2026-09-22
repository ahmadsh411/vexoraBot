<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();

            // ============================================================
            //  الفاعل
            // ============================================================
            $table->foreignId('admin_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // ============================================================
            //  الإجراء
            // ============================================================
            $table->string('action', 100)->index()
                ->comment('user.activate, user.deactivate, balance.add, wheel.grant_spin, ...');

            $table->string('target_type', 100)->nullable()
                ->comment('Model class أو نوع الهدف');

            $table->unsignedBigInteger('target_id')->nullable()
                ->comment('ID الهدف');

            // ============================================================
            //  التغييرات
            // ============================================================
            $table->json('changes')->nullable()
                ->comment('{field: {old, new}}');

            $table->text('reason')->nullable();

            // ============================================================
            //  البيانات الإضافية
            // ============================================================
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // ============================================================
            //  Indexes
            // ============================================================
            $table->index(['admin_id', 'created_at'], 'admin_act_admin_date');
            $table->index(['action', 'created_at'], 'admin_act_action_date');
            $table->index(['target_type', 'target_id'], 'admin_act_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};
