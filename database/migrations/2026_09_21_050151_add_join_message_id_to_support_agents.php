<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_agents', function (Blueprint $table) {
            $table->bigInteger('join_message_id')->nullable()->after('channels_joined_at');
        });
    }

    public function down(): void
    {
        Schema::table('support_agents', function (Blueprint $table) {
            $table->dropColumn('join_message_id');
        });
    }
};
