<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'deposit_bonus.enabled'],
            [
                'value'       => '0',
                'type'        => 'bool',
                'group'       => 'deposit_bonus',
                'label'       => 'تفعيل مكافآت الإيداع',
                'description' => 'تفعيل/تعطيل مكافآت الإيداع التلقائية',
                'is_editable' => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'deposit_bonus.percent'],
            [
                'value'       => '10',
                'type'        => 'int',
                'group'       => 'deposit_bonus',
                'label'       => 'نسبة مكافأة الإيداع (%)',
                'description' => 'النسبة المئوية المضافة كرصيد عند كل إيداع',
                'is_editable' => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'deposit_bonus.enabled',
            'deposit_bonus.percent',
        ])->delete();
    }
};
