<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensitive_access_logs', function (Blueprint $table) {
            $table->id();

            // ============================================================
            //  الفاعل
            // ============================================================
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('المستخدم صاحب البيانات');

            $table->foreignId('accessed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('من وصل للبيانات');

            // ============================================================
            //  نوع البيانات الحساسة
            // ============================================================
            $table->enum('resource_type', [
                'password',
                'pin',
                'api_key',
                'token',
                'personal_data',
            ])->index();

            $table->unsignedBigInteger('resource_id')->nullable();

            // ============================================================
            //  السياق
            // ============================================================
            $table->string('action', 50)->default('read')
                ->comment('read, decrypt, update, delete');

            $table->text('reason')->nullable();

            // ============================================================
            //  معلومات الطلب
            // ============================================================
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamp('created_at')->useCurrent()->index();

            // ============================================================
            //  Indexes
            // ============================================================
            $table->index(['user_id', 'created_at'], 'sal_user_created');
            $table->index(['accessed_by', 'created_at'], 'sal_accessor_created');
            $table->index(['resource_type', 'action'], 'sal_resource_action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensitive_access_logs');
    }
};
