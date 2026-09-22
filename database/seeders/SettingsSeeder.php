<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // ═══════════════════════════════════════════════════════════
        //  🗑️ إزالة الإعدادات القديمة
        // ═══════════════════════════════════════════════════════════
        Setting::whereIn('key', ['bot_name', 'support_channel'])->delete();

        $settings = [
            // ═══════════════════════════════════════════════════
            //  General
            // ═══════════════════════════════════════════════════
            [
                'key'         => 'bot_name_prefix',
                'value'       => 'Vexora',
                'type'        => 'string',
                'group'       => 'general',
                'label'       => 'بادئة اسم المستخدم',
                'description' => 'البادئة التي تُضاف لأسماء المستخدمين الجدد (مثال: Vexora → Vexora_ahmad)',
                'is_editable' => true,
            ],
            [
                'key'         => 'general_channel',
                'value'       => '@VexoraChannel',
                'type'        => 'string',
                'group'       => 'general',
                'label'       => 'القناة العامة',
                'description' => 'القناة العامة للبوت (تُستخدم للإشعارات العامة)',
                'is_editable' => true,
            ],

            // ═══════════════════════════════════════════════════
            //  Finance
            // ═══════════════════════════════════════════════════
            [
                'key'         => 'withdraw_fee_percent',
                'value'       => '2',
                'type'        => 'decimal',
                'group'       => 'finance',
                'label'       => 'عمولة السحب %',
                'description' => 'نسبة العمولة على السحوبات',
                'is_editable' => true,
            ],
            [
                'key'         => 'withdraws_enabled',
                'value'       => '1',
                'type'        => 'bool',
                'group'       => 'finance',
                'label'       => 'تفعيل السحب',
                'description' => 'تفعيل/تعطيل خدمة السحب',
                'is_editable' => true,
            ],

            // ═══════════════════════════════════════════════════
            //  Maintenance
            // ═══════════════════════════════════════════════════
            [
                'key'         => 'maintenance_mode',
                'value'       => '0',
                'type'        => 'bool',
                'group'       => 'maintenance',
                'label'       => 'وضع الصيانة',
                'description' => 'تفعيل وضع الصيانة',
                'is_editable' => false,
            ],
            [
                'key'         => 'maintenance_message',
                'value'       => 'البوت تحت الصيانة، عد قريبًا',
                'type'        => 'string',
                'group'       => 'maintenance',
                'label'       => 'رسالة الصيانة',
                'description' => 'الرسالة التي تظهر أثناء الصيانة',
                'is_editable' => true,
            ],
        ];

        foreach ($settings as $data) {
            Setting::updateOrCreate(
                ['key' => $data['key']],
                array_merge([
                    'type'        => 'string',
                    'group'       => 'general',
                    'is_editable' => true,
                ], $data),
            );
        }

        Setting::flush();

        $this->command->info('✅ تم تحديث الإعدادات.');
    }
}
