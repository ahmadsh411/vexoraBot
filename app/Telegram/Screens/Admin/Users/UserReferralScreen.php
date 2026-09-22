<?php

namespace App\Telegram\Screens\Admin\Users;

use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\ReferralSetting;
use App\Models\User;
use App\Services\ReferralCycleService;
use App\Services\ReferralService;

class UserReferralScreen
{
    /**
     * نص صفحة الإحالات.
     */
    public static function text(User $user): string
    {
        $settings     = ReferralSetting::current();
        $cycleService = app(ReferralCycleService::class);

        // ═══════════════════════════════════════════════════
        //  النوع والنسب
        // ═══════════════════════════════════════════════════
        $typeLabel = match ($user->referral_type) {
            Referral::TYPE_INSTANT => '⚡ فوري',
            Referral::TYPE_CYCLE   => '📅 دوري',
            default                => '❔ لم يُحدَّد',
        };

        if ($user->referral_type === Referral::TYPE_INSTANT) {
            $percentLabel = 'L1: ' . $settings->instant_level_1_percent . '%  ·  L2: ' . $settings->instant_level_2_percent . '%';
        } elseif ($user->referral_type === Referral::TYPE_CYCLE) {
            $percentLabel = 'L1: ' . $settings->cycle_level_1_percent . '%  ·  L2: ' . $settings->cycle_level_2_percent . '%';
        } else {
            $percentLabel = '—';
        }

        // ═══════════════════════════════════════════════════
        //  الإحصائيات
        // ═══════════════════════════════════════════════════
        $l1Count = Referral::forReferrer($user->id)->level1()->count();
        $l2Count = Referral::forReferrer($user->id)->level2()->count();
        $totalReferrals = $l1Count + $l2Count;

        $totalEarned = (float) $user->referral_earnings;

        $instantEarned = (float) ReferralReward::forReferrer($user->id)
            ->instant()->paid()->sum('amount');

        $cycleEarned = (float) ReferralReward::forReferrer($user->id)
            ->cycle()->paid()->sum('amount');

        // ═══════════════════════════════════════════════════
        //  الدورة الحالية
        // ═══════════════════════════════════════════════════
        $cycle = $cycleService->getCurrentCycle();
        $cycleBlock = '❌ لا توجد دورة نشطة';

        if ($cycle) {
            $expected = $cycleService->getExpectedCycleReward($user);

            $cycleBlock = implode("\n", [
                '📅  ' . $cycle->duration_label,
                '',
                '🔥  حرق L1  ·  <code>' . number_format($expected['burn_l1'], 2) . ' NSP (ليرة سورية جديدة)</code>',
                '🔥  حرق L2  ·  <code>' . number_format($expected['burn_l2'], 2) . ' NSP (ليرة سورية جديدة)</code>',
                '💰  المتوقع  ·  <code>' . number_format($expected['reward'], 2) . ' NSP (ليرة سورية جديدة)</code>',
            ]);
        }

        // ═══════════════════════════════════════════════════
        //  الرابط
        // ═══════════════════════════════════════════════════
        $linkBlock = '❌ لم يُولَّد بعد';
        if ($user->referral_code) {
            $link = $user->referral_link ?? '—';
            $linkBlock = '<code>' . $link . '</code>';
        }

        // ═══════════════════════════════════════════════════
        //  البناء
        // ═══════════════════════════════════════════════════
        $username = htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8');

        return implode("\n", [
            '<b>🎯 إحالات</b>  —  <code>' . $username . '</code>',
            '',
            '🆔  <b>المعرف</b>  ·  <code>#' . $user->id . '</code>',
            '📊  <b>النوع</b>  ·  ' . $typeLabel,
            '📈  <b>النسب</b>  ·  ' . $percentLabel,
            '',
            '<b>🔗 رابط الإحالة</b>',
            $linkBlock,
            '',
            '<b>👥 المُحالون</b>',
            '',
            '🥇  المستوى الأول  ·  <code>' . number_format($l1Count) . '</code>',
            '🥈  المستوى الثاني  ·  <code>' . number_format($l2Count) . '</code>',
            '📊  الإجمالي  ·  <code>' . number_format($totalReferrals) . '</code>',
            '',
            '<b>💰 المكاسب</b>',
            '',
            '⚡  فورية  ·  <code>' . number_format($instantEarned, 2) . ' NSP (ليرة سورية جديدة)</code>',
            '📅  دورية  ·  <code>' . number_format($cycleEarned, 2) . ' NSP (ليرة سورية جديدة)</code>',
            '📊  الإجمالي  ·  <code>' . number_format($totalEarned, 2) . ' NSP (ليرة سورية جديدة)</code>',
            '',
            '<b>📅 الدورة الحالية</b>',
            '',
            $cycleBlock,
            '',
            '<i>👇 اختر إجراءً</i>',
        ]);
    }

    /**
     * نص قائمة المُحالين.
     */
    public static function referralsListText(User $user): string
    {
        $l1Referrals = Referral::forReferrer($user->id)
            ->level1()
            ->with('referred:id,username,first_name,last_name,is_active')
            ->latest()
            ->get();

        $l2Referrals = Referral::forReferrer($user->id)
            ->level2()
            ->with('referred:id,username,first_name,last_name,is_active')
            ->latest()
            ->get();

        $username = htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8');

        if ($l1Referrals->isEmpty() && $l2Referrals->isEmpty()) {
            return implode("\n", [
                '<b>👥 المُحالون</b>',
                '',
                '❌ لا يوجد مُحالون حتى الآن.',
            ]);
        }

        $lines = [
            '<b>👥 المُحالون</b>  —  <code>' . $username . '</code>',
            '',
        ];

        // L1
        if ($l1Referrals->isNotEmpty()) {
            $lines[] = '<b>🥇 المستوى الأول (' . number_format($l1Referrals->count()) . ')</b>';
            $lines[] = '';

            foreach ($l1Referrals as $referral) {
                $referred = $referral->referred;
                if (! $referred) continue;

                $name = trim(($referred->first_name ?? '') . ' ' . ($referred->last_name ?? ''));
                if ($name === '') $name = $referred->username;

                $status = $referred->is_active ? '🟢' : '🔴';

                $lines[] = $status . '  <b>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</b>';
                $lines[] = '   📛  <code>' . htmlspecialchars($referred->username, ENT_QUOTES, 'UTF-8') . '</code>';
                $lines[] = '   💰  إيداع  ·  <code>' . number_format((float) $referral->total_deposited, 2) . ' NSP (ليرة سورية جديدة)</code>';
                $lines[] = '   🎁  مكسب  ·  <code>' . number_format((float) $referral->total_earned, 2) . ' NSP (ليرة سورية جديدة)</code>';
                $lines[] = '   📅  ' . $referral->created_at?->format('Y-m-d');
                $lines[] = '';
            }
        }

        // L2
        if ($l2Referrals->isNotEmpty()) {
            $lines[] = '<b>🥈 المستوى الثاني (' . number_format($l2Referrals->count()) . ')</b>';
            $lines[] = '';

            foreach ($l2Referrals as $referral) {
                $referred = $referral->referred;
                if (! $referred) continue;

                $name = trim(($referred->first_name ?? '') . ' ' . ($referred->last_name ?? ''));
                if ($name === '') $name = $referred->username;

                $status = $referred->is_active ? '🟢' : '🔴';

                $lines[] = $status . '  <b>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</b>';
                $lines[] = '   📛  <code>' . htmlspecialchars($referred->username, ENT_QUOTES, 'UTF-8') . '</code>';
                $lines[] = '   💰  إيداع  ·  <code>' . number_format((float) $referral->total_deposited, 2) . ' NSP (ليرة سورية جديدة)</code>';
                $lines[] = '   🎁  مكسب  ·  <code>' . number_format((float) $referral->total_earned, 2) . ' NSP (ليرة سورية جديدة)</code>';
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * نص سجل المكافآت.
     */
    public static function rewardsListText(User $user, string $filter = 'all'): string
    {
        $query = ReferralReward::forReferrer($user->id)
            ->paid()
            ->with('referred:id,username')
            ->latest();

        if ($filter === 'instant') {
            $query->instant();
            $title = '⚡ <b>المكافآت الفورية</b>';
        } elseif ($filter === 'cycle') {
            $query->cycle();
            $title = '📅 <b>مكافآت الدورات</b>';
        } else {
            $title = '📜 <b>سجل المكافآت</b>';
        }

        $rewards = $query->limit(30)->get();

        if ($rewards->isEmpty()) {
            return implode("\n", [
                $title,
                '',
                '❌ لا يوجد سجل حتى الآن.',
            ]);
        }

        $total = (float) $rewards->sum('amount');

        $lines = [
            $title,
            '',
            '📊  <b>العدد</b>  ·  <code>' . number_format($rewards->count()) . '</code>',
            '💰  <b>الإجمالي</b>  ·  <code>' . number_format($total, 2) . ' NSP (ليرة سورية جديدة)</code>',
            '',
        ];

        foreach ($rewards as $index => $reward) {
            $number = $index + 1;

            $icon = match ($reward->type) {
                'instant' => '⚡',
                'cycle'   => '📅',
                default   => '🎁',
            };

            $levelIcon = $reward->level === 1 ? '🥇' : '🥈';

            $lines[] = '<b>' . $number . '.</b>  ' . $icon . ' ' . $levelIcon . '  <code>' . number_format((float) $reward->amount, 2) . ' ' . $reward->currency . '</code>';

            if ($reward->referred) {
                $lines[] = '   👤  من  ·  <code>' . htmlspecialchars($reward->referred->username, ENT_QUOTES, 'UTF-8') . '</code>';
            } else {
                $lines[] = '   👥  من  ·  عدة مستخدمين';
            }

            $lines[] = '   📊  النسبة  ·  <code>' . $reward->commission_percent . '%</code>';
            $lines[] = '   📅  ' . $reward->paid_at?->format('Y-m-d H:i');
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * نص تأكيد إعادة تعيين النوع.
     */
    public static function confirmResetTypeText(User $user): string
    {
        $current = match ($user->referral_type) {
            'instant' => '⚡ فوري',
            'cycle'   => '📅 دوري',
            default   => '❔ لم يُحدَّد',
        };

        $username = htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8');

        return implode("\n", [
            '<b>⚠️ تأكيد إعادة تعيين النوع</b>',
            '',
            '👤  <b>المستخدم</b>  ·  <code>' . $username . '</code>',
            '📊  <b>النوع الحالي</b>  ·  ' . $current,
            '',
            '<b>⚠️ تنبيه</b>',
            '',
            'سيتم إعادة تعيين النوع إلى فارغ.',
            'لن يستطيع المستخدم استقبال إحالات جديدة',
            'حتى يختار نوعاً جديداً.',
            '',
            '<i>📝 الإحالات الحالية لن تتأثر — فقط النوع المستقبلي.</i>',
        ]);
    }
}
