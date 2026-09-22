<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ichancy_accounts', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            
            $table->string('ichancy_player_id')->unique();
            $table->string('ichancy_username')->index();
            $table->string('ichancy_password_encrypted');
            $table->string('currency', 10)->default('NSP');
            
            $table->decimal('balance_cache', 15, 2)->default(0);
            $table->timestamp('last_synced_at')->nullable();
            
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ichancy_accounts');
    }
};
