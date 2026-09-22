<?php

namespace App\Helpers;

class ErrorMessages
{
    /**
     * تحويل الأخطاء التقنية إلى رسائل ودّية للمستخدم.
     */
    public static function friendly(?string $error, string $context = 'general'): string
    {
        if (! $error) {
            return self::defaultMessage($context);
        }

        $error = strtolower($error);

        // ✅ أخطاء الشبكة
        if (
            str_contains($error, 'curl') ||
            str_contains($error, 'timeout') ||
            str_contains($error, 'timed out') ||
            str_contains($error, 'connection') ||
            str_contains($error, 'network') ||
            str_contains($error, 'could not resolve') ||
            str_contains($error, 'ssl')
        ) {
            return '⚠️ تعذّر الاتصال بالخدمة. الرجاء المحاولة لاحقاً.';
        }

        // ✅ أخطاء Cloudflare
        if (
            str_contains($error, 'cloudflare') ||
            str_contains($error, '403') ||
            str_contains($error, '429') ||
            str_contains($error, 'rate limit')
        ) {
            return '⚠️ الخدمة مشغولة حالياً. الرجاء المحاولة بعد قليل.';
        }

        // ✅ أخطاء 500
        if (str_contains($error, '500') || str_contains($error, 'internal server')) {
            return '⚠️ خدمة النظام غير متاحة مؤقتاً.';
        }

        // ✅ رصيد غير كافٍ
        if (
            str_contains($error, 'رصيد') ||
            str_contains($error, 'balance') ||
            str_contains($error, 'insufficient')
        ) {
            return '⚠️ رصيدك غير كافٍ لإتمام العملية.';
        }

        // ✅ حد أدنى / أقصى
        if (str_contains($error, 'الحد الأدنى') || str_contains($error, 'minimum')) {
            return '⚠️ المبلغ أقل من الحد الأدنى.';
        }
        if (str_contains($error, 'الحد الأقصى') || str_contains($error, 'maximum')) {
            return '⚠️ المبلغ أكبر من الحد الأقصى.';
        }

        // ✅ عملة غير مدعومة
        if (str_contains($error, 'currency') || str_contains($error, 'عملة')) {
            return '⚠️ العملة غير مدعومة حالياً.';
        }

        // ✅ معرف اللاعب
        if (str_contains($error, 'player') || str_contains($error, 'لاعب')) {
            return '⚠️ تعذّر الوصول إلى حسابك. الرجاء التواصل مع الدعم.';
        }

        // ✅ fallback
        return self::defaultMessage($context);
    }

    /**
     * رسالة افتراضية حسب السياق.
     */
    private static function defaultMessage(string $context): string
    {
        return match ($context) {
            'deposit'  => '⚠️ تعذّر إتمام الشحن. الرجاء المحاولة لاحقاً.',
            'withdraw' => '⚠️ تعذّر إتمام السحب. الرجاء المحاولة لاحقاً.',
            'ichancy'  => '⚠️ تعذّر إتمام العملية في نظام IChancy.',
            'transfer' => '⚠️ تعذّر إتمام التحويل. الرجاء المحاولة لاحقاً.',
            'register' => '⚠️ تعذّر إنشاء الحساب. الرجاء المحاولة لاحقاً.',
            default    => '⚠️ حدث خطأ غير متوقع. الرجاء المحاولة لاحقاً.',
        };
    }
}
