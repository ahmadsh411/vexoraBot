<?php

namespace App\Telegram\Screens\User;

use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\ReferralSetting;
use App\Models\User;
use App\Services\ReferralCycleService;

class UserReferralScreen
{
    // ============================================================
    //  🎨 Helper — تسمية العملة
    // ============================================================
    private static function currencyLabel(string $code): string
    {
        return match (strtoupper($code)) {
            'NSP' => 'NSP (ليرة سورية جديدة)',
            'USD' => 'USD (دولار)',
            'NPS' => 'NPS (ليرة سورية قديمة)',
            'SYP' => 'SYP (ليرة سورية)',
            default => $code,
        };
    }

    // ============================================================
    //  🏠 الصفحة الرئيسية
    // ============================================================

    public static function main(User $user): string
    {
        $settings = ReferralSetting::current();

        if (! $settings->is_active) {
            return implode("\n", [
                '🤝 <b>نظام الإحالات</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🔴 <b>معطّل حالياً</b>',
                '',
                '⏳ سيتم الإعلان عند إعادة التفعيل.',
            ]);
        }

        // ✅ الإحصائيات
        $l1Count = Referral::forReferrer($user->id)->level1()->count();
        $l2Count = Referral::forReferrer($user->id)->level2()->count();
        $total   = $l1Count + $l2Count;

        $earnings = (float) $user->referral_earnings;
        $link     = $user->referral_link;

        // ✅ نوع الإحالة
        $typeLabel = $user->referral_type_label;

        // ✅ النسب الحالية
        if ($user->referral_type === Referral::TYPE_INSTANT) {
            $percentL1 = $settings->instant_level_1_percent;
            $percentL2 = $settings->instant_level_2_percent;
            $label = '⚡ فوري';
        } else {
            $percentL1 = $settings->cycle_level_1_percent;
            $percentL2 = $settings->cycle_level_2_percent;
            $label = '📅 دوري';
        }

        return implode("\n", [
            '🤝 <b>نظام الإحالات</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>النوع:</b> ' . $label,
            '',
            '💰 <b>أرباحك:</b> <b>' . number_format($earnings, 2) . '</b> NSP (ليرة سورية جديدة)',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👥 <b>المُحالون:</b>',
            '├── 🥇 L1: <b>' . $l1Count . '</b>',
            '├── 🥈 L2: <b>' . $l2Count . '</b>',
            '└── 📊 الإجمالي: <b>' . $total . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🔗 <b>رابط الإحالة:</b>',
            '└── <code>' . htmlspecialchars($link ?? '—', ENT_QUOTES, 'UTF-8') . '</code>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💡 <b>كيف تكسب؟</b>',
            '• شارك رابطك مع أصدقائك',
            '• عند تسجيلهم → تحصل على إحالة',
            '• عند إيداعهم → تحصل على نسبة',
            '',
            '📊 <b>النسب:</b>',
            '• L1: ' . $percentL1 . '%',
            '• L2: ' . $percentL2 . '%',
        ]);
    }

    // ============================================================
    //  👥 قائمة المُحالين
    // ============================================================

    public static function referralsList(User $user): string
    {
        $l1 = Referral::forReferrer($user->id)
            ->level1()
            ->with('referred:id,username,first_name,last_name,is_active')
            ->latest()
            ->get();

        $l2 = Referral::forReferrer($user->id)
            ->level2()
            ->with('referred:id,username,first_name,last_name,is_active')
            ->latest()
            ->get();

        if ($l1->isEmpty() && $l2->isEmpty()) {
            return implode("\n", [
                '👥 <b>المُحالون</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '❌ لا يوجد مُحالون بعد.',
                '',
                '🔗 شارك رابطك لتبدأ!',
            ]);
        }

        $lines = [
            '👥 <b>المُحالون</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
        ];

        if ($l1->isNotEmpty()) {
            $lines[] = '🥇 <b>Level 1 (' . $l1->count() . '):</b>';
            $lines[] = '';

            foreach ($l1 as $ref) {
                $referred = $ref->referred;
                if (! $referred) continue;

                $status = $referred->is_active ? '🟢' : '🔴';
                $lines[] = $status . ' <code>' . htmlspecialchars($referred->username, ENT_QUOTES, 'UTF-8') . '</code>';
                $lines[] = '   💰 إيداع: ' . number_format((float) $ref->total_deposited, 0) . ' NSP (ليرة سورية جديدة)';
                $lines[] = '   🎁 مكسب: ' . number_format((float) $ref->total_earned, 0) . ' NSP (ليرة سورية جديدة)';
                $lines[] = '';
            }
        }

        if ($l2->isNotEmpty()) {
            $lines[] = '🥈 <b>Level 2 (' . $l2->count() . '):</b>';
            $lines[] = '';

            foreach ($l2 as $ref) {
                $referred = $ref->referred;
                if (! $referred) continue;

                $status = $referred->is_active ? '🟢' : '🔴';
                $lines[] = $status . ' <code>' . htmlspecialchars($referred->username, ENT_QUOTES, 'UTF-8') . '</code>';
                $lines[] = '   💰 إيداع: ' . number_format((float) $ref->total_deposited, 0) . ' NSP (ليرة سورية جديدة)';
                $lines[] = '   🎁 مكسب: ' . number_format((float) $ref->total_earned, 0) . ' NSP (ليرة سورية جديدة)';
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    // ============================================================
    //  🧾 سجل المكافآت
    // ============================================================

    public static function rewardsList(User $user, string $filter = 'all'): string
    {
        $query = ReferralReward::forReferrer($user->id)
            ->paid()
            ->with('referred:id,username')
            ->latest();

        $title = match ($filter) {
            'instant' => '⚡ <b>المكافآت الفورية</b>',
            'cycle'   => '📅 <b>مكافآت الدورات</b>',
            default   => '📜 <b>سجل المكافآت</b>',
        };

        if ($filter === 'instant') {
            $query->instant();
        } elseif ($filter === 'cycle') {
            $query->cycle();
        }

        $rewards = $query->limit(30)->get();

        if ($rewards->isEmpty()) {
            return implode("\n", [
                $title,
                '━━━━━━━━━━━━━━━━━━',
                '',
                '❌ لا يوجد سجل.',
            ]);
        }

        $total = (float) $rewards->sum('amount');

        $lines = [
            $title,
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>العدد:</b> ' . $rewards->count(),
            '💰 <b>الإجمالي:</b> ' . number_format($total, 2) . ' NSP (ليرة سورية جديدة)',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
        ];

        foreach ($rewards as $index => $reward) {
            $icon = match ($reward->type) {
                'instant' => '⚡',
                'cycle'   => '📅',
                default   => '🎁',
            };

            $levelIcon = $reward->level === 1 ? '🥇' : '🥈';
            $currencyLabel = self::currencyLabel($reward->currency);

            $lines[] = ($index + 1) . '. ' . $icon . ' ' . $levelIcon . ' <b>'
                . number_format((float) $reward->amount, 2) . ' ' . $currencyLabel . '</b>';

            if ($reward->referred) {
                $lines[] = '   👤 من: <code>'
                    . htmlspecialchars($reward->referred->username, ENT_QUOTES, 'UTF-8')
                    . '</code>';
            } else {
                $lines[] = '   👥 دورة كاملة';
            }

            $lines[] = '   📊 النسبة: ' . $reward->commission_percent . '%';
            $lines[] = '   📅 ' . $reward->paid_at?->format('Y-m-d H:i');
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
