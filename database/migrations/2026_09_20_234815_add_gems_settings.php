<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key'         => 'gems.enabled',
                'value'       => '0',
                'type'        => 'bool',
                'group'       => 'gems',
                'label'       => 'تفعيل نظام الجواهر',
                'description' => 'تفعيل/تعطيل نظام الجواهر بالكامل',
                'is_editable' => true,
            ],
            [
                'key'         => 'gems.min_deposit_nsp',
                'value'       => '1000',
                'type'        => 'int',
                'group'       => 'gems',
                'label'       => 'الحد الأدنى للاكتساب (NSP)',
                'description' => 'أقل مبلغ إيداع بالـ NSP لاكتساب جوهرة',
                'is_editable' => true,
            ],
            [
                'key'         => 'gems.min_deposit_usd',
                'value'       => '1',
                'type'        => 'decimal',
                'group'       => 'gems',
                'label'       => 'الحد الأدنى للاكتساب (USD)',
                'description' => 'أقل مبلغ إيداع بالـ USD لاكتساب جوهرة',
                'is_editable' => true,
            ],
            [
                'key'         => 'gems.exchange_min_gems',
                'value'       => '10',
                'type'        => 'int',
                'group'       => 'gems',
                'label'       => 'الحد الأدنى لاستبدال الرصيد',
                'description' => 'أقل عدد جواهر لاستبدالها برصيد',
                'is_editable' => true,
            ],
            [
                'key'         => 'gems.exchange_value_nsp',
                'value'       => '10000',
                'type'        => 'int',
                'group'       => 'gems',
                'label'       => 'قيمة استبدال الرصيد (NSP)',
                'description' => 'قيمة الاستبدال بالـ NSP مقابل الحد الأدنى من الجواهر',
                'is_editable' => true,
            ],
            [
                'key'         => 'gems.wheel_min_gems',
                'value'       => '5',
                'type'        => 'int',
                'group'       => 'gems',
                'label'       => 'الحد الأدنى لفتح العجلة',
                'description' => 'عدد الجواهر المطلوبة لفتح لفة عجلة',
                'is_editable' => true,
            ],
            [
                'key'         => 'gems.wheel_spins',
                'value'       => '1',
                'type'        => 'int',
                'group'       => 'gems',
                'label'       => 'عدد لفات العجلة المكتسبة',
                'description' => 'عدد لفات العجلة المكتسبة مقابل الجواهر',
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
            'gems.enabled',
            'gems.min_deposit_nsp',
            'gems.min_deposit_usd',
            'gems.exchange_min_gems',
            'gems.exchange_value_nsp',
            'gems.wheel_min_gems',
            'gems.wheel_spins',
        ])->delete();
    }
};
