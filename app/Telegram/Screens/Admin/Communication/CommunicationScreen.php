<?php

namespace App\Telegram\Screens\Admin\Communication;

use App\Models\User;

class CommunicationScreen
{
    // ============================================================
    //  الصفحة الرئيسية
    // ============================================================
    public static function text(): string
    {
        $totalUsers = User::whereNotNull('telegram_id')->count();

        return implode("\n", [
            '📢 <b>مركز التواصل</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👥 إجمالي المستخدمين: <b>' . number_format($totalUsers) . '</b>',
            '',
            'اختر الإجراء المطلوب:',
        ]);
    }

    // ============================================================
    //  بث جماعي
    // ============================================================
    public static function broadcastText(): string
    {
        return implode("\n", [
            '📢 <b>بث جماعي</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎯 اختر الفئة المستهدفة:',
            '',
            '⚠️ <i>سيتم إرسال الرسالة لكل مستخدم في الفئة المختارة.</i>',
        ]);
    }

    // ============================================================
    //  طلب نص الرسالة
    // ============================================================
    public static function askForText(string $target): string
    {
        return implode("\n", [
            '✍️ <b>نص الرسالة</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎯 <b>الفئة:</b> ' . self::targetLabel($target),
            '👥 <b>المستهدفون:</b> <b>' . number_format(self::targetCount($target)) . '</b>',
            '',
            '📝 أرسل الآن نص الرسالة',
            '💡 <i>يدعم HTML للتنسيق</i>',
            '',
            '↩️ /cancel للإلغاء',
        ]);
    }

    // ============================================================
    //  تأكيد الإرسال الجماعي
    // ============================================================
    public static function confirmText(string $target, string $text): string
    {
        return implode("\n", [
            '📢 <b>تأكيد البث</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎯 <b>الفئة:</b> ' . self::targetLabel($target),
            '👥 <b>المستهدفون:</b> <b>' . number_format(self::targetCount($target)) . '</b>',
            '',
            '📝 <b>معاينة الرسالة:</b>',
            '╭─────────────────────',
            '│ ' . $text,
            '╰─────────────────────',
            '',
            '⚠️ <b>هل أنت متأكد من الإرسال؟</b>',
        ]);
    }

    // ============================================================
    //  بدء البث (رسالة واحدة فقط)
    // ============================================================
    public static function startedText(string $target, int $count): string
    {
        return implode("\n", [
            '🚀 <b>جارٍ البث...</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎯 <b>الفئة:</b> ' . self::targetLabel($target),
            '👥 <b>المستهدفون:</b> <b>' . number_format($count) . '</b>',
            '',
            '⏳ <i>سيتم الإرسال في الخلفية</i>',
            '🔔 <i>سيصلك إشعار عند الانتهاء</i>',
        ]);
    }

    // ============================================================
    //  رسالة لمستخدم — طلب ID
    // ============================================================
    public static function singleAskId(): string
    {
        return implode("\n", [
            '✉️ <b>رسالة لمستخدم</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📝 أرسل <b>Telegram ID</b> الخاص بالمستخدم:',
            '',
            '💡 <i>مثال: 8335709957</i>',
            '',
            '↩️ /cancel للإلغاء',
        ]);
    }

    // ============================================================
    //  طلب نص الرسالة الفردية
    // ============================================================
    public static function singleAskText(string $telegramId): string
    {
        return implode("\n", [
            '✍️ <b>نص الرسالة</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👤 <b>المُستلم:</b>',
            '└── <code>' . $telegramId . '</code>',
            '',
            '📝 أرسل الآن نص الرسالة',
            '💡 <i>يدعم HTML للتنسيق</i>',
            '',
            '↩️ /cancel للإلغاء',
        ]);
    }

    // ============================================================
    //  تأكيد الإرسال الفردي
    // ============================================================
    public static function singleConfirm(string $telegramId, string $text): string
    {
        return implode("\n", [
            '✉️ <b>تأكيد الإرسال</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👤 <b>المُستلم:</b>',
            '└── <code>' . $telegramId . '</code>',
            '',
            '📝 <b>معاينة الرسالة:</b>',
            '╭─────────────────────',
            '│ ' . $text,
            '╰─────────────────────',
            '',
            '⚠️ <b>هل أنت متأكد من الإرسال؟</b>',
        ]);
    }

    // ============================================================
    //  إشعار نجاح الإرسال الفردي
    // ============================================================
    public static function singleSent(string $telegramId): string
    {
        return implode("\n", [
            '✅ <b>تم الإرسال</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👤 <b>المُستلم:</b>',
            '└── <code>' . $telegramId . '</code>',
            '',
            '🔔 <i>سيصلك إشعار بالنتيجة</i>',
        ]);
    }

    // ============================================================
    //  Helpers
    // ============================================================
    public static function targetCount(string $target): int
    {
        return match ($target) {
            'all' => User::whereNotNull('telegram_id')->count(),
            'balance_nsp' => User::whereNotNull('telegram_id')
                ->whereHas('wallet', fn($q) => $q->where('balance_nsp', '>', 0))
                ->count(),
            'balance_usd' => User::whereNotNull('telegram_id')
                ->whereHas('wallet', fn($q) => $q->where('balance_usd', '>', 0))
                ->count(),
            'referrers' => User::whereNotNull('telegram_id')->where('referrals_count', '>', 0)->count(),
            'new' => User::whereNotNull('telegram_id')->where('created_at', '>=', now()->subDays(7))->count(),
            'inactive' => User::whereNotNull('telegram_id')
                ->where(function ($q) {
                    $q->whereNull('last_login_at')->orWhere('last_login_at', '<', now()->subDays(30));
                })->count(),
            'has_account' => User::whereNotNull('telegram_id')->has('paymentAccounts')->count(),
            default => 0,
        };
    }

    public static function targetLabel(string $target): string
    {
        return match ($target) {
            'all' => '👥 الكل',
            'balance_nsp' => '💰 لديهم رصيد NSP (ليرة سورية جديدة)',
            'balance_usd' => '💵 لديهم رصيد USD',
            'referrers' => '🎯 لديهم إحالات',
            'new' => '🆕 الجدد (7 أيام)',
            'inactive' => '😴 الخاملون (30 يوم)',
            'has_account' => '🏦 لديهم حساب دفع',
            default => '❓ غير معروف',
        };
    }
}
