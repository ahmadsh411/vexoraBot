<?php

namespace Database\Seeders;

use App\Models\Wheel;
use App\Models\WheelPrize;
use Illuminate\Database\Seeder;

class WheelSeeder extends Seeder
{
    public function run(): void
    {
        $wheel = Wheel::firstOrCreate(
            ['id' => 1],
            [
                'name'                       => 'عجلة الحظ',
                'description'                => 'عجلة حظ VEXORA',
                'is_active'                  => true,
                'deposit_syp_threshold'      => 50000,
                'min_deposit_syp_threshold'  => 1000,
                'max_deposit_syp_threshold'  => 1000000,
                'deposit_usd_threshold'      => 1,
                'min_deposit_usd_threshold'  => 0.5,
                'max_deposit_usd_threshold'  => 100,
                'referral_threshold'         => 5,
                'min_referral_threshold'     => 1,
                'max_referral_threshold'     => 50,
                'daily_limit'                => 10,
                'min_daily_limit'            => 1,
                'max_daily_limit'            => 100,
                'max_stored_spins'           => 100,
                'auto_grant_on_deposit'      => true,
            ],
        );

        if ($wheel->prizes()->exists()) {
            $this->command->warn('⚠️ الجوائز موجودة مسبقاً.');
            return;
        }

        $prizes = [
            ['name' => '50 ل.س',   'icon' => '💵', 'value' => 50,   'weight' => 30, 'type' => 'balance'],
            ['name' => '100 ل.س',  'icon' => '💵', 'value' => 100,  'weight' => 25, 'type' => 'balance'],
            ['name' => '200 ل.س',  'icon' => '💰', 'value' => 200,  'weight' => 15, 'type' => 'balance'],
            ['name' => '500 ل.س',  'icon' => '💰', 'value' => 500,  'weight' => 10, 'type' => 'balance'],
            ['name' => '1000 ل.س', 'icon' => '💎', 'value' => 1000, 'weight' => 5,  'type' => 'balance'],
            ['name' => 'فارغة',     'icon' => '😢', 'value' => 0,    'weight' => 10, 'type' => 'empty'],
            ['name' => 'لفة إضافية', 'icon' => '♻️', 'value' => 0,   'weight' => 5,  'type' => 'recycle'],
        ];

        foreach ($prizes as $index => $data) {
            WheelPrize::create([
                'wheel_id'   => $wheel->id,
                'name'       => $data['name'],
                'icon'       => $data['icon'],
                'value'      => $data['value'],
                'currency'   => 'SYP',
                'weight'     => $data['weight'],
                'type'       => $data['type'],
                'color'      => '#4CAF50',
                'sort_order' => $index,
                'is_active'  => true,
            ]);
        }

        $this->command->info('✅ تم إنشاء العجلة مع 7 جوائز.');
    }
}
