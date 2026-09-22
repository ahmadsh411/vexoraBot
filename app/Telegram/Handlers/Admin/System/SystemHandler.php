<?php

namespace App\Telegram\Handlers\Admin\System;

use App\Models\Setting;
use App\Models\User;
use App\Telegram\Keyboards\AdminsKeyboard\System\SystemKeyboard;
use App\Telegram\Screens\Admin\System\SystemScreen;
use SergiX44\Nutgram\Nutgram;

class SystemHandler
{
    // ============================================================
    //  index
    // ============================================================

    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $user = $this->getCurrentUser($bot);

        $this->safeEdit(
            $bot,
            SystemScreen::main(),
            SystemKeyboard::main($user?->isSuperAdmin() ?? false),
        );
    }

    // ============================================================
    //  groups
    // ============================================================

    public function general(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            SystemScreen::group('general', 'الإعدادات العامة'),
            SystemKeyboard::forGroup('general'),
        );
    }

    public function finance(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            SystemScreen::group('finance', 'إعدادات المالية'),
            SystemKeyboard::forGroup('finance'),
        );
    }

    // ============================================================
    //  maintenance
    // ============================================================

    public function maintenance(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $enabled = (bool) Setting::get('maintenance_mode', false);

        $this->safeEdit(
            $bot,
            SystemScreen::maintenance($enabled),
            SystemKeyboard::maintenance($enabled),
        );
    }

    public function confirmMaintenanceOn(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            SystemScreen::confirmMaintenanceOn(),
            SystemKeyboard::confirmMaintenance(true),
        );
    }

    public function confirmMaintenanceOff(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            SystemScreen::confirmMaintenanceOff(),
            SystemKeyboard::confirmMaintenance(false),
        );
    }

    public function doMaintenanceOn(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        Setting::set('maintenance_mode', true);

        $message = Setting::get('maintenance_message', 'البوت تحت الصيانة');

        app(\App\Services\NotificationService::class)->notifyMaintenance($message);

        $this->maintenance($bot);
    }

    public function doMaintenanceOff(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        Setting::set('maintenance_mode', false);

        app(\App\Services\NotificationService::class)->notifyMaintenanceEnded();

        $this->maintenance($bot);
    }

    // ============================================================
    //  info
    // ============================================================

    public function info(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $user = $this->getCurrentUser($bot);

        $this->safeEdit(
            $bot,
            SystemScreen::info(),
            SystemKeyboard::main($user?->isSuperAdmin() ?? false),
        );
    }

    // ============================================================
    //  cancel
    // ============================================================

    public function cancel(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $user = $this->getCurrentUser($bot);

        $this->safeEdit(
            $bot,
            SystemScreen::main(),
            SystemKeyboard::main($user?->isSuperAdmin() ?? false),
        );
    }

    public function noop(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
    }

    // ============================================================
    //  toggleSetting
    // ============================================================

    public function toggleSetting(Nutgram $bot, string $key): void
    {
        $this->safeAnswer($bot);

        $setting = Setting::where('key', $key)->first();

        if (! $setting || $setting->type !== 'bool') {
            return;
        }

        $current = (bool) $setting->typed_value;

        $admin = User::where('telegram_id', $bot->userId())->first();

        app(\App\Services\SettingService::class)->setByAdmin(
            key: $key,
            value: ! $current,
            admin: $admin,
            reason: 'Toggle from system panel',
        );

        match ($setting->group) {
            'finance'     => $this->finance($bot),
            'maintenance' => $this->maintenance($bot),
            'general'     => $this->general($bot),
            default       => $this->index($bot),
        };
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function getCurrentUser(Nutgram $bot): ?User
    {
        return User::where('telegram_id', $bot->userId())->first();
    }

    private function safeAnswer(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
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
