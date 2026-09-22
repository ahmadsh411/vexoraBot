<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wheel;
use App\Models\WheelPrize;
use App\Services\Concerns\HasLockingHelpers;

class WheelAdminService
{
    use HasLockingHelpers;

    // ============================================================
    //  الإعدادات
    // ============================================================

    /**
     * تحديث إعدادات العجلة.
     */
    public function updateSettings(Wheel $wheel, array $settings, User $admin): bool
    {
        $allowed = [
            'deposit_syp_threshold',
            'min_deposit_syp_threshold',
            'max_deposit_syp_threshold',
            'deposit_usd_threshold',
            'min_deposit_usd_threshold',
            'max_deposit_usd_threshold',
            'referral_threshold',
            'min_referral_threshold',
            'max_referral_threshold',
            'daily_limit',
            'min_daily_limit',
            'max_daily_limit',
            'max_stored_spins',
            'auto_grant_on_deposit',
            'is_active',
        ];

        $changes = [];

        foreach ($settings as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                continue;
            }

            $old = $wheel->{$key};
            $wheel->{$key} = $value;

            $changes[$key] = ['old' => $old, 'new' => $value];
        }

        if (empty($changes)) {
            return false;
        }

        $wheel->save();

        \App\Models\AdminAction::log(
            adminId: $admin->id,
            action: 'wheel.update_settings',
            targetType: Wheel::class,
            targetId: $wheel->id,
            changes: $changes,
        );

        return true;
    }

    public function toggleActive(Wheel $wheel, User $admin): bool
    {
        $old = $wheel->is_active;

        $wheel->update(['is_active' => ! $old]);

        \App\Models\AdminAction::log(
            adminId: $admin->id,
            action: 'wheel.toggle_active',
            targetType: Wheel::class,
            targetId: $wheel->id,
            changes: ['is_active' => ['old' => $old, 'new' => ! $old]],
        );

        return $wheel->is_active;
    }

    // ============================================================
    //  الجوائز
    // ============================================================

    /**
     * إنشاء جائزة جديدة.
     */
    public function createPrize(Wheel $wheel, array $data, User $admin): WheelPrize
    {
        $prize = WheelPrize::create([
            'wheel_id'  => $wheel->id,
            'name'      => $data['name'],
            'icon'      => $data['icon'] ?? '🎁',
            'color'     => $data['color'] ?? '#4CAF50',
            'value'     => $data['value'] ?? 0,
            'currency'  => strtoupper($data['currency'] ?? 'SYP'),
            'type'      => $data['type'] ?? WheelPrize::TYPE_BALANCE,
            'weight'    => (int) ($data['weight'] ?? 10),
            'sort_order' => (int) ($data['sort_order'] ?? $wheel->prizes()->count()),
            'is_active' => true,
        ]);

        \App\Models\AdminAction::log(
            adminId: $admin->id,
            action: 'wheel.create_prize',
            targetType: WheelPrize::class,
            targetId: $prize->id,
            changes: ['prize' => $data],
        );

        return $prize;
    }

    /**
     * تحديث جائزة.
     */
    public function updatePrize(WheelPrize $prize, array $data, User $admin): bool
    {
        $changes = [];

        foreach ($data as $key => $value) {
            if (! in_array($key, ['name', 'icon', 'color', 'value', 'currency', 'type', 'weight', 'sort_order', 'is_active'], true)) {
                continue;
            }

            $old = $prize->{$key};

            if ($old != $value) {
                $changes[$key] = ['old' => $old, 'new' => $value];
                $prize->{$key} = $value;
            }
        }

        if (empty($changes)) {
            return false;
        }

        $prize->save();

        \App\Models\AdminAction::log(
            adminId: $admin->id,
            action: 'wheel.update_prize',
            targetType: WheelPrize::class,
            targetId: $prize->id,
            changes: $changes,
        );

        return true;
    }

    /**
     * حذف جائزة.
     */
    public function deletePrize(WheelPrize $prize, User $admin): bool
    {
        \App\Models\AdminAction::log(
            adminId: $admin->id,
            action: 'wheel.delete_prize',
            targetType: WheelPrize::class,
            targetId: $prize->id,
            changes: ['prize' => $prize->toArray()],
        );

        return (bool) $prize->delete();
    }

    /**
     * تبديل حالة جائزة.
     */
    public function togglePrize(WheelPrize $prize, User $admin): bool
    {
        $old = $prize->is_active;

        $prize->update(['is_active' => ! $old]);

        \App\Models\AdminAction::log(
            adminId: $admin->id,
            action: 'wheel.toggle_prize',
            targetType: WheelPrize::class,
            targetId: $prize->id,
            changes: ['is_active' => ['old' => $old, 'new' => ! $old]],
        );

        return $prize->is_active;
    }

    /**
     * تحديث نسبة جائزة.
     */
    public function updatePrizeWeight(WheelPrize $prize, float $percent, User $admin): bool
    {
        if ($percent < 0 || $percent > 100) {
            throw new \InvalidArgumentException('النسبة يجب أن تكون بين 0 و 100');
        }

        $old = $prize->weight;
        $new = (int) round($percent);

        if ($old === $new) {
            return false;
        }

        $prize->update(['weight' => $new]);

        \App\Models\AdminAction::log(
            adminId: $admin->id,
            action: 'wheel.update_prize_weight',
            targetType: WheelPrize::class,
            targetId: $prize->id,
            changes: ['weight' => ['old' => $old, 'new' => $new]],
        );

        return true;
    }
}
