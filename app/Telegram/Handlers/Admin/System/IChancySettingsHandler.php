<?php

namespace App\Telegram\Handlers\Admin\System;

use App\Models\Setting;
use App\Models\User;
use App\Telegram\Conversations\Admin\System\EditIChancySettingConversation;
use App\Telegram\Keyboards\AdminsKeyboard\System\IChancySettingsKeyboard;
use App\Telegram\Screens\Admin\System\IChancySettingsScreen;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class IChancySettingsHandler
{
    // ============================================================
    //  🏠 عرض القسم
    // ============================================================
    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            IChancySettingsScreen::text(),
            IChancySettingsKeyboard::make(),
        );
    }

    // ============================================================
    //  🔄 تفعيل/تعطيل + إشعارات
    // ============================================================
    public function toggle(Nutgram $bot): void
    {
        $current  = (bool) Setting::get('ichancy.enabled', true);
        $newValue = ! $current;

        // ✅ 1. تحديث الإعداد
        Setting::where('key', 'ichancy.enabled')->update([
            'value' => $newValue ? '1' : '0',
        ]);

        cache()->forget('setting.ichancy.enabled');

        // ✅ 2. الرد الفوري على الأدمن
        $this->safeAnswer(
            $bot,
            $newValue ? '🟢 جاري التفعيل...' : '🔴 جاري التعطيل...',
        );

        Log::info('IChancy settings: toggle', [
            'admin_id' => $bot->userId(),
            'enabled'  => $newValue,
        ]);

        // ✅ 3. تحديث الشاشة
        $this->safeEdit(
            $bot,
            IChancySettingsScreen::text(),
            IChancySettingsKeyboard::make(),
        );

        // ✅ 4. إرسال الإشعارات
        if ($newValue) {
            $this->notifyUsersAboutActivation($bot);
            $this->notifyChannelAboutActivation($bot);
        } else {
            $this->notifyUsersAboutDeactivation($bot);
            $this->notifyChannelAboutDeactivation($bot);
        }
    }

    // ============================================================
    //  ✏️ بدء تعديل قيمة
    // ============================================================
    public function edit(Nutgram $bot, string $key): void
    {
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            $this->safeAnswer($bot, '❌ الإعداد غير موجود');
            return;
        }

        if (! $setting->is_editable) {
            $this->safeAnswer($bot, '🔒 غير قابل للتعديل');
            return;
        }

        $this->safeAnswer($bot);

        Cache::put(
            "ichancy.edit.{$bot->userId()}",
            $key,
            now()->addMinutes(10),
        );

        EditIChancySettingConversation::begin($bot);
    }

    // ============================================================
    //  📢 إشعار المستخدمين — التعطيل
    // ============================================================
    private function notifyUsersAboutDeactivation(Nutgram $bot): void
    {
        $users = User::whereNotNull('telegram_id')
            ->where('is_active', true)
            ->whereHas('ichancyAccount')
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $text = implode("\n", [
            '🔴 <b>إشعار مهم</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '⚠️ <b>خدمة IChancy معطلة مؤقتاً</b>',
            '',
            '🛠 <b>السبب:</b>',
            '└── صيانة مؤقتة من الإدارة',
            '',
            '⏸ <b>الخدمات المتوقفة حالياً:</b>',
            '├── 🎮 شحن IChancy',
            '└── 💸 سحب من IChancy',
            '',
            '✅ <b>الخدمات المتاحة:</b>',
            '├── 💰 شحن المحفظة',
            '├── 📤 سحب من المحفظة',
            '└── 🎡 عجلة الحظ',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🔄 <i>سيتم إشعارك عند عودة الخدمة</i>',
            '',
            '💬 للاستفسار: تواصل مع الدعم',
        ]);

        $sent   = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                $bot->sendMessage(
                    text: $text,
                    chat_id: $user->telegram_id,
                    parse_mode: 'HTML',
                );
                $sent++;
                usleep(50000);
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Failed to notify user about IChancy deactivation', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        Log::info('IChancy deactivation notifications sent', [
            'sent'   => $sent,
            'failed' => $failed,
        ]);
    }

    // ============================================================
    //  📢 إشعار المستخدمين — التفعيل
    // ============================================================
    private function notifyUsersAboutActivation(Nutgram $bot): void
    {
        $users = User::whereNotNull('telegram_id')
            ->where('is_active', true)
            ->whereHas('ichancyAccount')
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $text = implode("\n", [
            '🟢 <b>إشعار مهم</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '✅ <b>عادت خدمة IChancy للعمل</b>',
            '',
            '🎮 <b>يمكنك الآن:</b>',
            '├── 🎮 شحن IChancy',
            '└── 💸 سحب من IChancy',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎯 <i>ابدأ الآن من القائمة الرئيسية</i>',
        ]);

        $sent   = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                $bot->sendMessage(
                    text: $text,
                    chat_id: $user->telegram_id,
                    parse_mode: 'HTML',
                );
                $sent++;
                usleep(50000);
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Failed to notify user about IChancy activation', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        Log::info('IChancy activation notifications sent', [
            'sent'   => $sent,
            'failed' => $failed,
        ]);
    }

    // ============================================================
    //  📢 إشعار القناة — التعطيل
    // ============================================================
    private function notifyChannelAboutDeactivation(Nutgram $bot): void
    {
        try {
            $text = implode("\n", [
                '🔴 <b>تعطيل خدمة IChancy</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '⏸ <b>الحالة:</b> معطلة مؤقتاً',
                '🛠 <b>السبب:</b> صيانة',
                '',
                '📊 <b>التأثير:</b>',
                '├── شحن IChancy: متوقف',
                '└── سحب IChancy: متوقف',
                '',
                '👮 <b>بواسطة:</b> الإدارة',
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            app(\App\Services\NotificationService::class)->notifyGeneralChannel(
                $bot,
                $text,
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about IChancy deactivation', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  📢 إشعار القناة — التفعيل
    // ============================================================
    private function notifyChannelAboutActivation(Nutgram $bot): void
    {
        try {
            $text = implode("\n", [
                '🟢 <b>تفعيل خدمة IChancy</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '✅ <b>الحالة:</b> مُفعّلة',
                '',
                '📊 <b>الخدمات المتاحة:</b>',
                '├── شحن IChancy: يعمل',
                '└── سحب IChancy: يعمل',
                '',
                '👮 <b>بواسطة:</b> الإدارة',
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            app(\App\Services\NotificationService::class)->notifyTransactionsChannel(
                $bot,
                $text,
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about IChancy activation', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  Helpers
    // ============================================================
    private function safeAnswer(Nutgram $bot, ?string $text = null): void
    {
        try {
            $bot->answerCallbackQuery(text: $text);
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

            try {
                $bot->sendMessage(
                    text: $text,
                    parse_mode: 'HTML',
                    reply_markup: $keyboard,
                );
            } catch (\Throwable $e2) {
                Log::warning('IChancySettingsHandler safeEdit failed', [
                    'error' => $e2->getMessage(),
                ]);
            }
        }
    }
}
