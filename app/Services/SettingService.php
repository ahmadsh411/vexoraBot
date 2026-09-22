<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;

class SettingService
{
    // ============================================================
    //  القراءة
    // ============================================================

    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        return (float) $this->get($key, $default);
    }

    public function getString(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    // ============================================================
    //  الكتابة
    // ============================================================

    public function set(string $key, mixed $value): void
    {
        Setting::set($key, $value);
    }

    /**
     * تعيين إعداد + تسجيل إجراء إداري.
     */
    public function setByAdmin(
        string $key,
        mixed $value,
        User $admin,
        ?string $reason = null,
    ): void {
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            throw new \RuntimeException("الإعداد {$key} غير موجود");
        }

        $oldValue = $setting->typed_value;

        Setting::set($key, $value);

        // ✅ تسجيل الإجراء
        \App\Models\AdminAction::log(
            adminId: $admin->id,
            action: \App\Models\AdminAction::ACTION_SETTING_UPDATE,
            targetType: Setting::class,
            targetId: $setting->id,
            changes: [
                'value' => [
                    'old' => $oldValue,
                    'new' => $value,
                ],
            ],
            reason: $reason,
        );
    }

    // ============================================================
    //  المجموعات
    // ============================================================

    public function byGroup(string $group): Collection
    {
        return Setting::byGroup($group);
    }

    public function groups(): array
    {
        return Setting::query()
            ->distinct()
            ->pluck('group')
            ->toArray();
    }

    // ============================================================
    //  Flush
    // ============================================================

    public function flush(): void
    {
        Setting::flush();
    }
}
