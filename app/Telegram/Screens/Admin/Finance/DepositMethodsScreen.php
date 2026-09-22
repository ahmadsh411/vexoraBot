<?php

namespace App\Telegram\Screens\Admin\Finance;

use App\Models\DepositMethod;

class DepositMethodsScreen
{
    /**
     * قائمة الطرق.
     */
    public static function listText(): string
    {
        $methods = DepositMethod::ordered()->get();

        if ($methods->isEmpty()) {
            return implode("\n", [
                '📥 <b>طرق الإيداع</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '❌ لا توجد طرق.',
                '',
                'اضغط "➕ إضافة طريقة جديدة"',
            ]);
        }

        $lines = [
            '📥 <b>طرق الإيداع</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 العدد: <b>' . $methods->count() . '</b>',
            '',
        ];

        foreach ($methods as $method) {
            $status = $method->is_active ? '🟢' : '🔴';
            $lines[] = "{$status} {$method->icon} <b>{$method->name}</b>";
            $lines[] = "   📞 <code>" . ($method->account_number ?: 'غير محدد') . "</code>";
            $lines[] = "   📊 " . $method->limits_label;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * تفاصيل طريقة.
     */
    public static function detailsText(DepositMethod $method): string
    {
        $lines = [
            $method->icon . ' <b>' . $method->name . '</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الحالة:</b> ' . $method->status_label,
            '💰 <b>العملة:</b> ' . $method->currency_icon . ' ' . $method->currency,
            '🔑 <b>الكود:</b> <code>' . $method->code . '</code>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📞 <b>رقم الحساب (للعرض):</b>',
            '<code>' . ($method->account_number ?: 'غير محدد') . '</code>',
            '',
            '👤 <b>اسم الحساب:</b>',
            '<code>' . ($method->account_name ?: 'غير محدد') . '</code>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🔐 <b>بيانات التحقق التلقائي:</b>',
            '',
            '📱 <b>GSM (سيرياتيل):</b>',
            '<code>' . ($method->receiver_gsm ?: 'غير محدد') . '</code>',
            '',
            '🏦 <b>عنوان شام كاش:</b>',
            '<code>' . ($method->receiver_address ?: 'غير محدد') . '</code>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الحدود:</b> ' . $method->limits_label,
            '💼 <b>العمولة:</b> ' . $method->commission_percent . '%',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📝 <b>التعليمات:</b>',
            $method->instructions ?: '-',
        ];

        return implode("\n", $lines);
    }

    /**
     * شاشة تأكيد الحذف.
     */
    public static function deleteConfirmText(DepositMethod $method): string
    {
        return implode("\n", [
            '⚠️ <b>تأكيد الحذف</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            'هل أنت متأكد من حذف:',
            $method->icon . ' <b>' . $method->name . '</b>',
            '',
            '⚠️ <b>هذا الإجراء لا يمكن التراجع عنه!</b>',
        ]);
    }
}
