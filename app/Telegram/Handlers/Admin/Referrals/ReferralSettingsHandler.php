<?php

namespace App\Telegram\Handlers\Admin\Referrals;

use App\Models\AdminAction;
use App\Models\ReferralSetting;
use App\Models\User;
use App\Telegram\Keyboards\AdminsKeyboard\Referrals\ReferralSettingsKeyboard;
use App\Telegram\Screens\Admin\Referrals\ReferralSettingsScreen;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class ReferralSettingsHandler
{
    // ============================================================
    //  index
    // ============================================================

    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            ReferralSettingsScreen::text(),
            ReferralSettingsKeyboard::make(),
        );
    }

    public function noop(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
    }

    // ============================================================
    //  adjust
    // ============================================================

    public function adjust(Nutgram $bot): void
    {
        $callbackData = $bot->callbackQuery()?->data;

        if (! $callbackData) {
            $this->safeAlert($bot, '❌ خطأ في البيانات.');
            return;
        }

        // admin.referrals.settings.{field}.{action}
        $parts = explode('.', $callbackData);

        if (count($parts) !== 5) {
            $this->safeAlert($bot, '❌ صيغة غير صالحة.');
            return;
        }

        $field = $parts[3];
        $action = $parts[4];

        $settings = ReferralSetting::current();
        $step = $field === 'cycle-days'
            ? ReferralSettingsKeyboard::STEP_DAYS
            : ReferralSettingsKeyboard::STEP_PERCENT;

        $sign = $action === 'inc' ? 1 : -1;
        $delta = $step * $sign;

        $column = match ($field) {
            'instant-l1' => 'instant_level_1_percent',
            'instant-l2' => 'instant_level_2_percent',
            'cycle-l1'   => 'cycle_level_1_percent',
            'cycle-l2'   => 'cycle_level_2_percent',
            'cycle-days' => 'cycle_days',
            default      => null,
        };

        if (! $column) {
            $this->safeAlert($bot, '❌ حقل غير معروف.');
            return;
        }

        $oldValue = (float) $settings->{$column};
        $newValue = $oldValue + $delta;

        if ($field === 'cycle-days') {
            $newValue = max(1, min(90, $newValue));
        } else {
            $newValue = max(0, min(100, $newValue));
        }

        $admin = User::where('telegram_id', $bot->userId())->first();

        $settings->update([
            $column      => $newValue,
            'updated_by' => $admin?->id,
        ]);

        Log::info('Referral setting updated', [
            'admin_id' => $bot->userId(),
            'field'    => $field,
            'old'      => $oldValue,
            'new'      => $newValue,
        ]);

        $label = match ($field) {
            'instant-l1' => '⚡ فوري L1',
            'instant-l2' => '⚡ فوري L2',
            'cycle-l1'   => '📅 دوري L1',
            'cycle-l2'   => '📅 دوري L2',
            'cycle-days' => '📆 مدة الدورة',
            default      => $field,
        };

        $unit = $field === 'cycle-days' ? ' يوم' : '%';

        $this->safeAlert($bot, "✅ {$label}: {$newValue}{$unit}");

        $this->safeEdit(
            $bot,
            ReferralSettingsScreen::text(),
            ReferralSettingsKeyboard::make(),
        );
    }

    // ============================================================
    //  toggleActive
    // ============================================================

    public function toggleActive(Nutgram $bot): void
    {
        $settings = ReferralSetting::current();
        $old = $settings->is_active;

        $settings->update(['is_active' => ! $old]);

        $this->safeAlert(
            $bot,
            $settings->is_active ? '🟢 تم التفعيل' : '🔴 تم التعطيل',
        );

        Log::info('Referral system toggled', [
            'admin_id'  => $bot->userId(),
            'is_active' => $settings->is_active,
        ]);

        $this->safeEdit(
            $bot,
            ReferralSettingsScreen::text(),
            ReferralSettingsKeyboard::make(),
        );
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function safeAnswer(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }
    }

    private function safeAlert(Nutgram $bot, string $text): void
    {
        try {
            $bot->answerCallbackQuery(text: $text, show_alert: true);
        } catch (\Throwable $e) {
        }
    }

    private function safeEdit(Nutgram $bot, string $text, $keyboard): void
    {
        try {
            $bot->editMessageText(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not modified')) {
                return;
            }

            $bot->sendMessage(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
            );
        }
    }
}
