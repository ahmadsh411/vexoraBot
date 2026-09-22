<?php

namespace App\Telegram\Screens\Admin\System;

use App\Models\User;

class AdminScreen
{
    // ============================================================
    //  القائمة الرئيسية
    // ============================================================
    public static function main(): string
    {
        $supers = User::where('is_admin', true)->where('is_super_admin', true)->count();
        $regulars = User::where('is_admin', true)->where('is_super_admin', false)->count();
        $total = $supers + $regulars;

        return implode("\n", [
            '👑 <b>إدارة الأدمن</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الإحصائيات:</b>',
            '├── 👑 مشرفون أساسيون: <b>' . $supers . '</b>',
            '├── 🛡️ أدمن عاديون: <b>' . $regulars . '</b>',
            '└── 📋 الإجمالي: <b>' . $total . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            'اختر أدمنًا للتعديل:',
        ]);
    }

    // ============================================================
    //  تفاصيل أدمن
    // ============================================================
    public static function adminDetails(User $admin): string
    {
        $role = $admin->is_super_admin
            ? '👑 مشرف أساسي'
            : '🛡️ أدمن عادي';

        return implode("\n", [
            '👤 <b>تفاصيل الأدمن</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📛 <b>الاسم:</b>',
            '<code>' . htmlspecialchars($admin->username, ENT_QUOTES, 'UTF-8') . '</code>',
            '',
            '🆔 <b>Telegram ID:</b>',
            '<code>' . $admin->telegram_id . '</code>',
            '',
            '🎭 <b>الدور:</b> ' . $role,
            '',
            '📊 <b>الحالة:</b> ' . ($admin->is_active ? '🟢 نشط' : '🔴 معطّل'),
            '',
            '📅 <b>تاريخ الإضافة:</b> ' . $admin->created_at?->format('Y-m-d'),
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👑 <b>الصلاحيات:</b>',
            $admin->is_super_admin
                ? '• كل شيء (كل الأقسام)'
                : '• كل الأقسام ما عدا إدارة الأدمن',
            '',
            '━━━━━━━━━━━━━━━━━━',
        ]);
    }

    // ============================================================
    //  طلب Telegram ID
    // ============================================================
    public static function askAdminId(): string
    {
        return implode("\n", [
            '➕ <b>إضافة أدمن</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📝 أرسل <b>Telegram ID</b> أو <b>@username</b>',
            '',
            'مثال:',
            '• <code>8335709957</code>',
            '• <code>@ahmad_support</code>',
            '',
            '⚠️ يجب أن يكون المستخدم مسجّلًا في البوت.',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            'أو /cancel للإلغاء.',
        ]);
    }

    // ============================================================
    //  تأكيد الحذف
    // ============================================================
    public static function confirmDelete(User $admin): string
    {
        return implode("\n", [
            '🗑️ <b>تأكيد الحذف</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            'هل أنت متأكد من إزالة صلاحيات:',
            '',
            '👤 <code>' . htmlspecialchars($admin->username, ENT_QUOTES, 'UTF-8') . '</code>',
            '',
            '🎭 <b>الدور الحالي:</b> ' . ($admin->is_super_admin ? '👑 مشرف أساسي' : '🛡️ أدمن عادي'),
            '',
            '⚠️ سيصبح مستخدمًا عاديًا ولن يستطيع الوصول للوحة التحكم.',
            '',
            '━━━━━━━━━━━━━━━━━━',
        ]);
    }
}
