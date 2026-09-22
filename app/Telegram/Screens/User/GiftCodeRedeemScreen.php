<?php

namespace App\Telegram\Screens\User;

class GiftCodeRedeemScreen
{
    public static function prompt(): string
    {
        return implode("\n", [
            '🎁 <b>استخدام كود هدية</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            'أرسل الكود الآن:',
            '',
            '💡 الصيغة: <code>GIFT_XXXXXXXX</code>',
            '',
            '⚡ سارع! الكود قد يُستخدم من شخص آخر.',
        ]);
    }

    public static function success(float $value, string $currency): string
    {
        return implode("\n", [
            '🎉 <b>مبروك!</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '✅ تم استبدال الكود بنجاح.',
            '',
            '💰 <b>المبلغ المضاف:</b>',
            '└── <b>' . number_format($value, 2) . ' ' . $currency . '</b>',
            '',
            '🎁 شكراً لاستخدامك البوت!',
        ]);
    }
}
