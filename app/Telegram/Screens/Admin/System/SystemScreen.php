<?php

namespace App\Telegram\Screens\Admin\System;

use App\Models\Setting;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class SystemScreen
{
    public static function main(): string
    {
        return implode("\n", [
            '⚙️ <b>النظام</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            'من هنا يمكنك التحكم بإعدادات البوت العامة،',
            'إدارة الأدمن، ومتابعة معلومات النظام.',
            '',
            'اختر الإجراء:',
        ]);
    }

    public static function group(string $group, string $title): string
    {
        $settings = Setting::byGroup($group);

        if ($settings->isEmpty()) {
            return "📝 <b>{$title}</b>\n\nلا توجد إعدادات.";
        }

        $lines = [
            '📝 <b>' . $title . '</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
        ];

        foreach ($settings as $setting) {
            $value = $setting->typed_value;

            if ($setting->type === 'bool') {
                $display = $value ? '🟢 مفعّل' : '🔴 معطّل';
            } else {
                $display = (string) $value;
            }

            $lines[] = '▪️ <b>' . ($setting->label ?? $setting->key) . ':</b>';
            $lines[] = '   <code>' . htmlspecialchars($display, ENT_QUOTES, 'UTF-8') . '</code>';
            $lines[] = '';
        }

        $lines[] = 'اختر إعدادًا لتعديله:';

        return implode("\n", $lines);
    }

    public static function maintenance(bool $enabled): string
    {
        $status = $enabled ? '🔴 <b>مُفعّل</b>' : '🟢 <b>مُعطّل</b>';

        return implode("\n", [
            '🔧 <b>وضع الصيانة</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            'الحالة الحالية: ' . $status,
            '',
            '📝 <b>رسالة الصيانة:</b>',
            '<i>' . htmlspecialchars(Setting::get('maintenance_message', ''), ENT_QUOTES, 'UTF-8') . '</i>',
            '',
            '⚠️ عند التفعيل، لن يستطيع المستخدمون استخدام البوت.',
        ]);
    }

    public static function info(): string
    {
        $usersCount = User::count();
        $adminsCount = User::where('is_admin', true)->count();
        $transactionsCount = Transaction::count();

        try {
            $dbSize = DB::select('SELECT SUM(data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = ?', [
                DB::getDatabaseName(),
            ])[0]->size ?? 0;
            $dbSizeMB = round($dbSize / 1024 / 1024, 2);
        } catch (\Throwable $e) {
            $dbSizeMB = 0;
        }

        return implode("\n", [
            '📊 <b>معلومات النظام</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👥 <b>المستخدمون:</b> ' . number_format($usersCount),
            '👑 <b>الأدمن:</b> ' . number_format($adminsCount),
            '💼 <b>العمليات:</b> ' . number_format($transactionsCount),
            '',
            '💾 <b>حجم القاعدة:</b> ' . $dbSizeMB . ' MB',
            '',
            '🐘 <b>PHP:</b> ' . PHP_VERSION,
            '🎼 <b>Laravel:</b> ' . app()->version(),
            '🕐 <b>الوقت:</b> ' . now()->format('Y-m-d H:i'),
        ]);
    }

    public static function askValue(string $key): string
    {
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            return "⚠️ الإعداد غير موجود.";
        }

        return implode("\n", [
            '✏️ <b>تعديل الإعداد</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📝 <b>' . ($setting->label ?? $setting->key) . '</b>',
            '',
            'القيمة الحالية:',
            '<code>' . htmlspecialchars((string) $setting->typed_value, ENT_QUOTES, 'UTF-8') . '</code>',
            '',
            '📤 أرسل القيمة الجديدة:',
            '',
            'أو /cancel للإلغاء.',
        ]);
    }

    // ============================================================
    //  تأكيد تفعيل الصيانة
    // ============================================================
    public static function confirmMaintenanceOn(): string
    {
        $message = \App\Models\Setting::get('maintenance_message', 'البوت تحت الصيانة');

        $userCount = \App\Models\User::whereNotNull('telegram_id')->count();

        return implode("\n", [
            '🔴 <b>تأكيد تفعيل الصيانة</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '⚠️ <b>تحذير:</b>',
            'عند التفعيل:',
            '• لن يستطيع المستخدمون استخدام البوت',
            '• سيتم إرسال <b>إشعار جماعي</b> لكل المستخدمين',
            '',
            '📊 <b>المستهدفون:</b> <b>' . number_format($userCount) . '</b> مستخدم',
            '',
            '📝 <b>رسالة الصيانة:</b>',
            '╭─────────────────────',
            '│ ' . $message,
            '╰─────────────────────',
            '',
            '⚠️ هل أنت متأكد؟',
        ]);
    }

    // ============================================================
    //  تأكيد إيقاف الصيانة
    // ============================================================
    public static function confirmMaintenanceOff(): string
    {
        return implode("\n", [
            '🟢 <b>تأكيد إيقاف الصيانة</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '✅ سيتمكن المستخدمون من استخدام البوت.',
            '',
            '📢 سيتم إرسال إشعار "انتهت الصيانة"',
            'لكل المستخدمين.',
            '',
            '⚠️ هل أنت متأكد؟',
        ]);
    }
}
