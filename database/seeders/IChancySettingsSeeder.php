<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class IChancySettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key'         => 'ichancy.enabled',
                'value'       => '1',
                'type'        => Setting::TYPE_BOOL,
                'group'       => 'ichancy',
                'label'       => 'تفعيل نظام IChancy',
                'description' => 'تفعيل/تعطيل ربط البوت بالكاشير',
                'is_editable' => true,
            ],
            [
                'key'         => 'ichancy.min_deposit',
                'value'       => '100',
                'type'        => Setting::TYPE_INT,
                'group'       => 'ichancy',
                'label'       => 'الحد الأدنى للإيداع',
                'description' => 'الحد الأدنى لشحن الرصيد في IChancy',
                'is_editable' => true,
            ],
            [
                'key'         => 'ichancy.max_deposit',
                'value'       => '100000',
                'type'        => Setting::TYPE_INT,
                'group'       => 'ichancy',
                'label'       => 'الحد الأقصى للإيداع',
                'description' => 'الحد الأقصى لشحن الرصيد',
                'is_editable' => true,
            ],
            [
                'key'         => 'ichancy.min_withdraw',
                'value'       => '100',
                'type'        => Setting::TYPE_INT,
                'group'       => 'ichancy',
                'label'       => 'الحد الأدنى للسحب',
                'description' => 'الحد الأدنى للسحب من IChancy',
                'is_editable' => true,
            ],
            [
                'key'         => 'ichancy.max_withdraw',
                'value'       => '50000',
                'type'        => Setting::TYPE_INT,
                'group'       => 'ichancy',
                'label'       => 'الحد الأقصى للسحب',
                'description' => 'الحد الأقصى للسحب',
                'is_editable' => true,
            ],
            [
                'key'         => 'ichancy.currency',
                'value'       => 'NSP',
                'type'        => Setting::TYPE_STRING,
                'group'       => 'ichancy',
                'label'       => 'العملة',
                'description' => 'عملة التعامل مع IChancy',
                'is_editable' => false,
            ],
        ];

        foreach ($settings as $data) {
            Setting::updateOrCreate(['key' => $data['key']], $data);
        }

        // امسح الكاش
        foreach ($settings as $data) {
            cache()->forget("setting.{$data['key']}");
        }

        $this->command->info('✅ IChancy settings seeded');
    }
}
