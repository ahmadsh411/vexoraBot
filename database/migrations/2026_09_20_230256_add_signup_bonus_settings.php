<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key'         => 'signup_bonus.enabled',
                'value'       => '0',
                'type'        => 'bool',
                'group'       => 'signup_bonus',
                'label'       => 'تفعيل مكافأة التسجيل',
                'description' => 'منح رصيد تلقائي عند التسجيل',
                'is_editable' => true,
            ],
            [
                'key'         => 'signup_bonus.amount_nsp',
                'value'       => '100',
                'type'        => 'int',
                'group'       => 'signup_bonus',
                'label'       => 'مبلغ NSP',
                'description' => 'مكافأة التسجيل بالـ NSP',
                'is_editable' => true,
            ],
            [
                'key'         => 'signup_bonus.amount_usd',
                'value'       => '0',
                'type'        => 'decimal',
                'group'       => 'signup_bonus',
                'label'       => 'مبلغ USD',
                'description' => 'مكافأة التسجيل بالـ USD',
                'is_editable' => true,
            ],
        ];

        foreach ($settings as $s) {
            DB::table('settings')->updateOrInsert(
                ['key' => $s['key']],
                array_merge($s, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'signup_bonus.enabled',
            'signup_bonus.amount_nsp',
            'signup_bonus.amount_usd',
        ])->delete();
    }
};
