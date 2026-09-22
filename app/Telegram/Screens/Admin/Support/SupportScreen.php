<?php

namespace App\Telegram\Screens\Admin\Support;

use App\Models\SupportAgent;

class SupportScreen
{
    // ============================================================
    //  🎧 للأدمن
    // ============================================================

    public static function admin(): string
    {
        $count  = SupportAgent::count();
        $active = SupportAgent::active()->count();

        return implode("\n", [
            '🛟 <b>مركز الدعم</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الإحصائيات:</b>',
            '├── إجمالي الداعمين: <b>' . $count . '</b>',
            '└── المتاحون: <b>' . $active . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            'اختر داعمًا للتعديل، أو أضف داعمًا جديدًا:',
        ]);
    }

    // ============================================================
    //  👤 تفاصيل داعم
    // ============================================================
    public static function agentDetails(SupportAgent $agent): string
    {
        $lines = [
            '👤 <b>تفاصيل الداعم</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎨 <b>الأيقونة:</b> ' . $agent->icon,
            '📝 <b>الاسم:</b> ' . htmlspecialchars($agent->name, ENT_QUOTES, 'UTF-8'),
            '🆔 <b>المعرّف:</b>',
            '<code>' . htmlspecialchars($agent->username, ENT_QUOTES, 'UTF-8') . '</code>',
        ];

        // ✅ Telegram ID (إن وُجد)
        if ($agent->telegram_id) {
            $lines[] = '📱 <b>Telegram ID:</b>';
            $lines[] = '<code>' . $agent->telegram_id . '</code>';
        }

        // ✅ حالة الاشتراك
        if ($agent->hasJoinedChannels()) {
            $lines[] = '';
            $lines[] = '📬 <b>الاشتراك:</b> ✅ تم';
            $lines[] = '<i>' . $agent->channels_joined_at->format('Y-m-d H:i') . '</i>';
        } else if ($agent->hasTelegramId()) {
            $lines[] = '';
            $lines[] = '📬 <b>الاشتراك:</b> ⏳ لم يُؤكَّد';
        } else {
            $lines[] = '';
            $lines[] = '📬 <b>الاشتراك:</b> ⚠️ لم يبدأ البوت';
        }

        if ($agent->description) {
            $lines[] = '';
            $lines[] = '📄 <b>الوصف:</b> ' . htmlspecialchars($agent->description, ENT_QUOTES, 'UTF-8');
        }

        $lines[] = '';
        $lines[] = '📊 <b>الحالة:</b> ' . $agent->status_label;
        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━';

        return implode("\n", $lines);
    }

    // ============================================================
    //  ➕ إضافة داعم — الخطوة 1 (الاسم)
    // ============================================================
    public static function askName(): string
    {
        return implode("\n", [
            '➕ <b>إضافة داعم</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📝 <b>الخطوة 1/2 — الاسم</b>',
            '',
            'أرسل اسم الداعم:',
            '',
            'مثال: <code>أحمد</code> أو <code>الدعم الفني</code>',
            '',
            '↩️ أو /cancel للإلغاء.',
        ]);
    }

    // ============================================================
    //  ➕ إضافة داعم — الخطوة 2 (المعرّف)
    // ============================================================
    public static function askUsername(): string
    {
        return implode("\n", [
            '➕ <b>إضافة داعم</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📝 <b>الخطوة 2/2 — المعرّف</b>',
            '',
            'أرسل معرّف الداعم (أحد الخيارين):',
            '',
            '1️⃣ <b>@username</b>',
            '   مثال: <code>@ahmad_dev</code>',
            '',
            '2️⃣ <b>Telegram ID</b>',
            '   مثال: <code>8335709957</code>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💡 <b>الأفضل:</b> <code>@username</code>',
            '   (يمكن الحصول عليه من بروفايل الداعم)',
            '',
            '↩️ أو /cancel للإلغاء.',
        ]);
    }

    // ============================================================
    //  🗑️ تأكيد الحذف
    // ============================================================
    public static function confirmDelete(SupportAgent $agent): string
    {
        return implode("\n", [
            '🗑️ <b>تأكيد الحذف</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            'هل أنت متأكد من حذف:',
            '',
            '📝 <b>' . htmlspecialchars($agent->full_name, ENT_QUOTES, 'UTF-8') . '</b>',
            '🆔 <code>' . htmlspecialchars($agent->username, ENT_QUOTES, 'UTF-8') . '</code>',
            '',
            '⚠️ <b>سيتم:</b>',
            '├── 🗑️ حذفه من قاعدة البيانات',
            '└── 🚪 إزالته من القنوات',
            '',
            '❌ لا يمكن التراجع عن هذا الإجراء.',
        ]);
    }

    // ============================================================
    //  👥 للمستخدم
    // ============================================================
    public static function user(): string
    {
        return implode("\n", [
            '🛟 <b>مركز الدعم</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📞 <b>تواصل مع الدعم:</b>',
            '',
            'اختر الداعم المناسب لاحتياجك:',
        ]);
    }
}
