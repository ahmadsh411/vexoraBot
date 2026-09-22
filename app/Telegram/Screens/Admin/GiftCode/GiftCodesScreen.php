<?php

namespace App\Telegram\Screens\Admin\GiftCode;

use App\Models\GiftCode;

class GiftCodesScreen
{
    /**
     * الصفحة الرئيسية لإدارة أكواد الهدايا
     */
    public static function index(): string
    {
        $stats = self::getStats();

        return implode("\n", [
            '🎁 <b>إدارة أكواد الهدايا</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الإحصائيات</b>',
            '',
            '🎫  إجمالي الأكواد   ·   <code>' . number_format($stats['total']) . '</code>',
            '🟢  نشطة            ·   <code>' . number_format($stats['active']) . '</code>',
            '🔴  مستخدمة         ·   <code>' . number_format($stats['used']) . '</code>',
            '⏰  منتهية           ·   <code>' . number_format($stats['expired']) . '</code>',
            '🚫  معطّلة          ·   <code>' . number_format($stats['disabled']) . '</code>',
            '',
            '💰 <b>إجمالي الموزّع</b>',
            '',
            '💵 NSP (ليرة سورية جديدة): <code>' . number_format($stats['distributed_nsp'], 2) . '</code>',
            '💵 USD: <code>' . number_format($stats['distributed_usd'], 2) . '</code>',
            '',
            '<i>👇 اختر إجراءً</i>',
        ]);
    }

    /**
     * رسالة قائمة الأكواد النشطة
     */
    public static function activeList($codes): string
    {
        if ($codes->isEmpty()) {
            return implode("\n", [
                '📭 <b>لا توجد أكواد نشطة حالياً</b>',
                '',
                '💡 يمكنك إنشاء كود جديد من الزر أدناه.',
            ]);
        }

        $lines = [
            '📋 <b>الأكواد النشطة</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
        ];

        foreach ($codes as $code) {
            $remaining = max(0, $code->max_uses - $code->used_count);
            $expiresIn = $code->expires_at
                ? $code->expires_at->diffForHumans(short: true, parts: 1)
                : '∞';

            $lines[] = '🎫 <code>' . $code->code . '</code>';
            $lines[] = '💰 ' . number_format($code->value, 2) . ' ' . $code->currency;
            $lines[] = '👥 ' . $remaining . ' / ' . $code->max_uses . ' | ⏰ ' . $expiresIn;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * رسالة سجل الاستبدالات
     */
    /**
     * رسالة سجل الاستبدالات (بدون هوية المستخدم)
     */
    public static function historyList($codes): string
    {
        if ($codes->isEmpty()) {
            return implode("\n", [
                '📭 <b>لا يوجد سجل استبدالات بعد</b>',
            ]);
        }

        $lines = [
            '📜 <b>سجل الاستبدالات</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
        ];

        foreach ($codes as $code) {
            $when = $code->updated_at?->diffForHumans(short: true, parts: 1) ?? '—';

            $lines[] = '🎫 <code>' . $code->code . '</code>';
            $lines[] = '💰 ' . number_format($code->value, 2) . ' ' . $code->currency;
            $lines[] = '👥 استُخدم ' . $code->used_count . ' / ' . $code->max_uses . ' مرة';
            $lines[] = '📅 ' . $when;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * الإحصائيات
     */
    protected static function getStats(): array
    {
        $total    = GiftCode::count();
        $active   = GiftCode::where('status', 'active')->count();
        $used     = GiftCode::where('status', 'used')->count();
        $expired  = GiftCode::where('status', 'expired')->count();
        $disabled = GiftCode::where('status', 'disabled')->count();

        $distNsp = GiftCode::where('currency', 'NSP')
            ->selectRaw('COALESCE(SUM(value * used_count), 0) as total')
            ->value('total') ?? 0;

        $distUsd = GiftCode::where('currency', 'USD')
            ->selectRaw('COALESCE(SUM(value * used_count), 0) as total')
            ->value('total') ?? 0;

        return [
            'total'           => $total,
            'active'          => $active,
            'used'            => $used,
            'expired'         => $expired,
            'disabled'        => $disabled,
            'distributed_nsp' => (float) $distNsp,
            'distributed_usd' => (float) $distUsd,
        ];
    }
}
