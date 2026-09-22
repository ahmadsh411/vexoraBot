<?php

namespace Database\Seeders;

use App\Models\ReferralSetting;
use Illuminate\Database\Seeder;

class ReferralSettingsSeeder extends Seeder
{
    public function run(): void
    {
        ReferralSetting::updateOrCreate(
            ['id' => 1],
            [
                'instant_level_1_percent' => 5.00,
                'instant_level_2_percent' => 2.00,
                'cycle_level_1_percent'   => 10.00,
                'cycle_level_2_percent'   => 3.00,
                'cycle_days'              => 10,
                'is_active'               => true,
            ],
        );

        $this->command->info('✅ تم إنشاء إعدادات الإحالة.');
    }
}
