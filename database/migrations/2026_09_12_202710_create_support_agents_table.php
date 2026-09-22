<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_agents', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100);
            $table->string('username', 100);
            $table->string('icon', 10)->default('👨');
            $table->string('description', 200)->nullable();

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'sa_active_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_agents');
    }
};
