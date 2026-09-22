<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_agents', function (Blueprint $table) {
            $table->bigInteger('telegram_id')->nullable()->after('username')->index();
            $table->timestamp('channels_joined_at')->nullable()->after('telegram_id');
        });
    }

    public function down(): void
    {
        Schema::table('support_agents', function (Blueprint $table) {
            $table->dropColumn(['telegram_id', 'channels_joined_at']);
        });
    }
};
