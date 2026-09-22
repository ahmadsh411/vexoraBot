<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ إضافة إعداد سعر العرض بشكل دائم
        DB::table('settings')->updateOrInsert(
            ['key' => 'ichancy.display_rate'],
            [
                'value'       => '100',
                'type'        => 'int',
                'group'       => 'ichancy',
                'label'       => 'سعر العرض (NSP → NPS)',
                'description' => 'سعر تحويل NSP إلى NPS في IChancy',
                'is_editable' => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        // ✅ إضافة ichancy.currency أيضًا (للتأمين)
        DB::table('settings')->updateOrInsert(
            ['key' => 'ichancy.currency'],
            [
                'value'       => 'NSP',
                'type'        => 'string',
                'group'       => 'ichancy',
                'label'       => 'عملة IChancy',
                'description' => 'العملة المستخدمة في IChancy',
                'is_editable' => false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'ichancy.display_rate',
        ])->delete();
        // ملاحظة: لا نحذف currency لأنه قديم
    }
};
