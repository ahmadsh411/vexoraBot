<?php

namespace App\Telegram\Screens\User;

use App\Models\GemTransaction;
use App\Models\User;
use App\Services\GemService;

class UserGemScreen
{
    /**
     * 🏠 الشاشة الرئيسية
     */
    public static function main(User $user, GemService $service): string
    {
        $balance   = $service->getBalanceInt($user);
        $minEx     = $service->getExchangeMinGems();
        $minWheel  = $service->getWheelMinGems();
        $valueNsp  = $service->getExchangeValueNsp();
        $spins     = $service->getWheelSpins();
        $minNsp    = $service->getMinDepositNsp();
        $minUsd    = $service->getMinDepositUsd();

        $canExchange = $balance >= $minEx;
        $canWheel    = $balance >= $minWheel;

        $values = $service->calculateExchange($user);

        $lines = [
            '💎 <b>جواهري</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💎 <b>رصيدك:</b> <b>' . number_format($balance) . '</b> جوهرة',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '📖 <b>كيف أحصل على الجواهر؟</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎁 <b>مع كل إيداع بقيمة:</b>',
            '├── 💰 ≥ ' . number_format($minNsp, 0) . ' NSP (ليرة سورية جديدة)',
            '└── 💵 ≥ ' . number_format($minUsd, 2) . ' USD (دولار)',
            '',
            '💡 <i>كل إيداع = جوهرة واحدة</i>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '✅ <b>ما يمكنك فعله الآن:</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
        ];

        // خيار الاستبدال
        if ($canExchange) {
            $lines[] = '💱 <b>استبدال برصيد:</b>';
            $lines[] = '   ├── 💰 NSP (ليرة سورية جديدة): <b>' . number_format($values['nsp'], 2) . '</b>';
            if ($values['usd'] > 0) {
                $lines[] = '   └── 💵 USD (دولار): <b>' . number_format($values['usd'], 2) . '</b>';
            }
        } else {
            $remaining = $minEx - $balance;
            $lines[] = '💱 <b>استبدال برصيد:</b>';
            $lines[] = '   🔒 تحتاج <b>' . $remaining . '</b> جوهرة إضافية';
            $lines[] = '   └── الحد: ' . $minEx . ' جواهر = ' . number_format($valueNsp, 0) . ' NSP (ليرة سورية جديدة)';
        }

        $lines[] = '';

        // خيار العجلة
        if ($canWheel) {
            $wheelCount = floor($balance / $minWheel);
            $lines[] = '🎡 <b>فتح العجلة:</b>';
            $lines[] = '   └── يمكنك فتح <b>' . $wheelCount . '</b> لفة!';
        } else {
            $remaining = $minWheel - $balance;
            $lines[] = '🎡 <b>فتح العجلة:</b>';
            $lines[] = '   🔒 تحتاج <b>' . $remaining . '</b> جوهرة إضافية';
            $lines[] = '   └── الحد: ' . $minWheel . ' جواهر = ' . $spins . ' لفة';
        }

        return implode("\n", $lines);
    }

    /**
     * 💱 شاشة اختيار العملة
     */
    public static function exchangeChoice(User $user, GemService $service): string
    {
        $balance = $service->getBalanceInt($user);
        $values  = $service->calculateExchange($user);

        return implode("\n", [
            '💱 <b>استبدال الجواهر</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💎 <b>رصيدك:</b> ' . number_format($balance) . ' جوهرة',
            '',
            '💰 <b>القيمة المتاحة:</b>',
            '├── 💰 NSP (ليرة سورية جديدة): <b>' . number_format($values['nsp'], 2) . '</b>',
            '└── 💵 USD (دولار): <b>' . number_format($values['usd'], 2) . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎯 <b>اختر العملة:</b>',
        ]);
    }

    /**
     * ✅ شاشة نجاح الاستبدال
     */
    public static function exchangeSuccess(string $currency, float $amount, int $gemsUsed): string
    {
        $currencyIcon = $currency === 'USD' ? '💵' : '💰';

        return implode("\n", [
            '🎉 <b>مبروك!</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '✅ <b>تم الاستبدال بنجاح</b>',
            '',
            '💎 <b>الجواهر المستخدمة:</b> ' . $gemsUsed,
            $currencyIcon . ' <b>المبلغ المضاف:</b> <b>' . number_format($amount, 2) . ' ' . $currency . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💚 <i>تمت الإضافة إلى محفظتك!</i>',
        ]);
    }

    /**
     * 🎡 شاشة نجاح فتح العجلة
     */
    public static function wheelSuccess(int $spins, int $gemsUsed): string
    {
        return implode("\n", [
            '🎉 <b>مبروك!</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '✅ <b>تم فتح العجلة</b>',
            '',
            '💎 <b>الجواهر المستخدمة:</b> ' . $gemsUsed,
            '🎰 <b>اللفات المكتسبة:</b> <b>' . $spins . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎡 <i>اذهب إلى عجلة الحظ وابدأ اللعب!</i>',
        ]);
    }

    /**
     * 📜 شاشة السجل
     */
    public static function history(User $user): string
    {
        $transactions = GemTransaction::where('user_id', $user->id)
            ->latestFirst()
            ->limit(10)
            ->get();

        $lines = [
            '📜 <b>سجل الجواهر</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
        ];

        if ($transactions->isEmpty()) {
            $lines[] = '⚠️ <i>لا يوجد سجل بعد</i>';
            return implode("\n", $lines);
        }

        foreach ($transactions as $t) {
            $sign = $t->amount > 0 ? '➕' : '➖';
            $abs  = abs($t->amount);

            $lines[] = $sign . ' <b>' . $abs . '</b> جوهرة — ' . $t->source_label;
            $lines[] = '   📅 ' . $t->created_at->format('Y-m-d H:i');

            if ($t->notes) {
                $lines[] = '   📝 <i>' . htmlspecialchars($t->notes, ENT_QUOTES, 'UTF-8') . '</i>';
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
