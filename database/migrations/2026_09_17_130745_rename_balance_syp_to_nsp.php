<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            if (Schema::hasColumn('wallets', 'balance_nsp')) {
                $table->renameColumn('balance_nsp', 'balance_nsp');
            }
            if (Schema::hasColumn('wallets', 'total_deposit_nsp')) {
                $table->renameColumn('total_deposit_nsp', 'total_deposit_nsp');
            }
            if (Schema::hasColumn('wallets', 'total_withdraw_nsp')) {
                $table->renameColumn('total_withdraw_nsp', 'total_withdraw_nsp');
            }
            if (Schema::hasColumn('wallets', 'total_commission_nsp')) {
                $table->renameColumn('total_commission_nsp', 'total_commission_nsp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            if (Schema::hasColumn('wallets', 'balance_nsp')) {
                $table->renameColumn('balance_nsp', 'balance_nsp');
            }
            if (Schema::hasColumn('wallets', 'total_deposit_nsp')) {
                $table->renameColumn('total_deposit_nsp', 'total_deposit_nsp');
            }
            if (Schema::hasColumn('wallets', 'total_withdraw_nsp')) {
                $table->renameColumn('total_withdraw_nsp', 'total_withdraw_nsp');
            }
            if (Schema::hasColumn('wallets', 'total_commission_nsp')) {
                $table->renameColumn('total_commission_nsp', 'total_commission_nsp');
            }
        });
    }
};
