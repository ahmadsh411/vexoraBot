<?php

namespace App\Telegram\Screens\User;

use App\Models\DepositMethod;
use App\Models\Transaction;
use App\Models\User;

class UserDepositScreen
{
    // ============================================================
    //  اختيار الطريقة
    // ============================================================

    public static function chooseMethod(): string
    {
        return implode("\n", [
            '💰 <b>شحن المحفظة</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💳 اختر <b>طريقة الإيداع</b>:',
            '',
            '⚠️ <i>سيتم مراجعة طلبك من قبل الإدارة.</i>',
        ]);
    }

    // ============================================================
    //  طلب المبلغ
    // ============================================================

    public static function askAmount(DepositMethod $method): string
    {
        $lines = [
            $method->icon . ' <b>' . htmlspecialchars($method->name, ENT_QUOTES, 'UTF-8') . '</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💰 <b>العملة:</b> ' . $method->currency_icon . ' ' . $method->currency,
            '',
            '📊 <b>الحدود المسموحة:</b>',
        ];

        if ((float) $method->min_amount > 0) {
            $lines[] = '├── 📉 <b>الأدنى:</b> <b>' . number_format((float) $method->min_amount, 0) . '</b> ' . $method->currency;
        } else {
            $lines[] = '├── 📉 <b>الأدنى:</b> لا يوجد';
        }

        if ((float) $method->max_amount > 0) {
            $lines[] = '└── 📈 <b>الأقصى:</b> <b>' . number_format((float) $method->max_amount, 0) . '</b> ' . $method->currency;
        } else {
            $lines[] = '└── 📈 <b>الأقصى:</b> لا يوجد';
        }

        if ($method->hasCommission()) {
            $lines[] = '';
            $lines[] = '💼 <b>العمولة:</b> ' . $method->commission_percent . '%';
        }

        $lines = array_merge($lines, [
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📞 <b>رقم الحساب:</b>',
            '└── <code>' . ($method->account_number ?: 'غير محدد') . '</code>',
            '',
            '👤 <b>اسم الحساب:</b>',
            '└── <code>' . ($method->account_name ?: 'غير محدد') . '</code>',
        ]);

        if ($method->isSyriatel() && $method->receiver_gsm) {
            $lines[] = '';
            $lines[] = '📱 <b>GSM المستقبل:</b>';
            $lines[] = '└── <code>' . $method->receiver_gsm . '</code>';
        }

        if ($method->isShamCash() && $method->receiver_address) {
            $lines[] = '';
            $lines[] = '🏦 <b>عنوان المحفظة:</b>';
            $lines[] = '└── <code>' . $method->receiver_address . '</code>';
        }

        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━';
        $lines[] = '';
        $lines[] = '📝 أرسل <b>المبلغ</b> الذي تريد إيداعه:';
        $lines[] = '';
        $lines[] = '↩️ أو /cancel للإلغاء.';

        return implode("\n", $lines);
    }

    // ============================================================
    //  طلب رقم العملية
    // ============================================================

    public static function askTransactionId(DepositMethod $method, float $amount): string
    {
        return implode("\n", [
            '🔢 <b>رقم العملية</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💰 <b>المبلغ:</b> <b>' . number_format($amount, 0) . '</b> ' . $method->currency,
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📝 أرسل <b>رقم العملية</b> الذي وصلك:',
            '',
            '💡 <i>ستجده في رسالة تأكيد التحويل</i>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🔍 <b>سيتم التحقق تلقائياً.</b>',
            '',
            '↩️ أو /cancel للإلغاء.',
        ]);
    }

    // ============================================================
    //  التأكيد
    // ============================================================

    public static function confirm(
        DepositMethod $method,
        float $amount,
        string $transactionId,
        bool $hasProof,
    ): string {
        $commission = $method->calculateCommission($amount);
        $net = $amount - $commission;

        $lines = [
            '✅ <b>تأكيد طلب الإيداع</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💳 <b>الطريقة:</b> ' . $method->full_name,
            '💰 <b>العملة:</b> ' . $method->currency,
            '',
            '📊 <b>المبلغ:</b> <b>' . number_format($amount, 0) . '</b> ' . $method->currency,
        ];

        if ($commission > 0) {
            $lines[] = '💼 <b>العمولة (' . $method->commission_percent . '%):</b> <b>' . number_format($commission, 0) . '</b> ' . $method->currency;
            $lines[] = '✅ <b>الصافي:</b> <b>' . number_format($net, 0) . '</b> ' . $method->currency;
        }

        $lines = array_merge($lines, [
            '',
            '🔢 <b>رقم العملية:</b>',
            '└── <code>' . htmlspecialchars($transactionId, ENT_QUOTES, 'UTF-8') . '</code>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '⚠️ <b>هل أنت متأكد؟</b>',
        ]);

        return implode("\n", $lines);
    }

    // ============================================================
    //  النجاح
    // ============================================================

    public static function successAuto(Transaction $transaction, DepositMethod $method): string
    {
        return implode("\n", [
            '🎉 <b>تم إيداع المبلغ بنجاح!</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🆔 <b>رقم العملية:</b>',
            '└── <code>#' . $transaction->id . '</code>',
            '',
            '🔖 <b>المرجع:</b>',
            '└── <code>' . $transaction->reference . '</code>',
            '',
            '💳 <b>الطريقة:</b> ' . $method->full_name,
            '💰 <b>المبلغ المُودع:</b> <b>' . number_format((float) $transaction->amount_to, 0) . '</b> ' . $method->currency,
            '',
            '📊 <b>الحالة:</b> 🟢 مكتمل',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '✅ تمت إضافة المبلغ إلى محفظتك فوراً.',
            '',
            '💚 شكراً لاستخدامك خدمتنا!',
        ]);
    }

    public static function success(Transaction $transaction, DepositMethod $method): string
    {
        return implode("\n", [
            '⏳ <b>تم إنشاء طلب الإيداع</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🆔 <b>رقم العملية:</b>',
            '└── <code>#' . $transaction->id . '</code>',
            '',
            '🔖 <b>المرجع:</b>',
            '└── <code>' . $transaction->reference . '</code>',
            '',
            '💳 <b>الطريقة:</b> ' . $method->full_name,
            '💰 <b>المبلغ:</b> <b>' . number_format((float) $transaction->amount_from, 0) . '</b> ' . $method->currency,
            '',
            '📊 <b>الحالة:</b> 🟡 بانتظار المراجعة',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '⏱ سيتم مراجعة طلبك خلال <b>5 - 30 دقيقة</b>.',
            '',
            '🔔 سيصلك إشعار عند الموافقة.',
        ]);
    }

    // ============================================================
    //  إشعارات الأدمن
    // ============================================================

    public static function adminNotification(
        Transaction $transaction,
        User $user,
        DepositMethod $method,
    ): string {
        $telegramLink = '<a href="tg://user?id=' . $user->telegram_id . '">'
            . '<code>' . $user->telegram_id . '</code></a>';

        $statusEmoji = $transaction->status === Transaction::STATUS_COMPLETED
            ? '🟢 مكتمل تلقائياً'
            : '🟡 معلّق (يحتاج مراجعة)';

        $lines = [
            '💰 <b>طلب إيداع جديد</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🆔 <b>العملية:</b> <code>#' . $transaction->id . '</code>',
            '🔖 <b>المرجع:</b> <code>' . $transaction->reference . '</code>',
            '',
            '👤 <b>المستخدم:</b>',
            '└── <code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
            '└── ' . $telegramLink,
            '',
            '💳 <b>الطريقة:</b> ' . $method->full_name,
            '',
            '📊 <b>المبلغ:</b> <b>' . number_format((float) $transaction->amount_from, 0) . '</b> ' . $method->currency,
        ];

        if ($transaction->commission_amount > 0) {
            $lines[] = '💼 <b>العمولة:</b> <b>' . number_format((float) $transaction->commission_amount, 0) . '</b>';
            $lines[] = '✅ <b>الصافي:</b> <b>' . number_format((float) $transaction->amount_to, 0) . '</b>';
        }

        $lines[] = '';
        $lines[] = '🔢 <b>رقم العملية:</b>';
        $lines[] = '└── <code>' . htmlspecialchars((string) $transaction->ichancy_transaction_id, ENT_QUOTES, 'UTF-8') . '</code>';
        $lines[] = '';
        $lines[] = '📊 <b>الحالة:</b> ' . $statusEmoji;
        $lines[] = '';
        $lines[] = '📅 <b>التاريخ:</b> ' . $transaction->created_at?->format('Y-m-d H:i');

        return implode("\n", $lines);
    }
}
