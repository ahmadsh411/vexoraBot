<?php

namespace App\Telegram\Handlers\Admin\Users;

use App\Models\User;
use App\Services\NotificationService;
use App\Telegram\Keyboards\AdminsKeyboard\Users\TrashedUsersKeyboard;
use App\Telegram\Screens\Admin\Users\TrashedUsersScreen;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class TrashedUsersHandler
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    // ============================================================
    //  📋 القائمة
    // ============================================================

    public function list(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            TrashedUsersScreen::listText(),
            TrashedUsersKeyboard::make(),
        );
    }

    // ============================================================
    //  👁️ العرض
    // ============================================================

    public function show(Nutgram $bot, string $id): void
    {
        $this->safeAnswer($bot);

        $user = User::withTrashed()->find((int) $id);

        if (! $user || ! $user->trashed()) {
            $this->safeAlert($bot, '❌ المستخدم غير موجود.');
            return;
        }

        $this->safeEdit(
            $bot,
            TrashedUsersScreen::detailsText($user),
            TrashedUsersKeyboard::detailsKeyboard($user),
        );
    }

    // ============================================================
    //  ♻️ استعادة
    // ============================================================

    public function restore(Nutgram $bot, string $id): void
    {
        $user = User::withTrashed()->find((int) $id);

        if (! $user || ! $user->trashed()) {
            $this->safeAlert($bot, '❌ المستخدم غير موجود.');
            return;
        }

        $user->restore();

        // ✅ تسجيل الإجراء
        try {
            \App\Models\AdminAction::log(
                adminId: $bot->userId(),
                action: \App\Models\AdminAction::ACTION_USER_RESTORE,
                targetType: User::class,
                targetId: $user->id,
            );
        } catch (\Throwable $e) {
            Log::warning('AdminAction log failed', ['error' => $e->getMessage()]);
        }

        $this->safeAlert($bot, '♻️ تم استعادة المستخدم.');

        // ✅ إشعارات
        $this->notifyChannelRestore($bot, $user);
        $this->notifyUserRestored($bot, $user);

        // ✅ إعادة العرض
        $this->safeEdit(
            $bot,
            TrashedUsersScreen::listText(),
            TrashedUsersKeyboard::make(),
        );
    }

    public function restoreAll(Nutgram $bot): void
    {
        $count = User::onlyTrashed()->count();

        if ($count === 0) {
            $this->safeAlert($bot, 'لا يوجد مستخدمون محذوفون.');
            return;
        }

        $users = User::onlyTrashed()->get();
        User::onlyTrashed()->restore();

        try {
            \App\Models\AdminAction::log(
                adminId: $bot->userId(),
                action: 'user.restore_all',
                targetType: User::class,
                changes: ['count' => $count],
            );
        } catch (\Throwable $e) {
        }

        $this->safeAlert($bot, "♻️ تم استعادة {$count} مستخدم.");

        foreach ($users as $user) {
            $this->notifyUserRestored($bot, $user);
        }

        $this->safeEdit(
            $bot,
            TrashedUsersScreen::listText(),
            TrashedUsersKeyboard::make(),
        );
    }

    // ============================================================
    //  💥 حذف نهائي
    // ============================================================

    public function forceConfirm(Nutgram $bot, string $id): void
    {
        $this->safeAnswer($bot);

        $user = User::withTrashed()->find((int) $id);

        if (! $user) {
            $this->safeAlert($bot, '❌ المستخدم غير موجود.');
            return;
        }

        $this->safeEdit(
            $bot,
            TrashedUsersScreen::confirmForceText($user),
            TrashedUsersKeyboard::confirmForceKeyboard($user),
        );
    }

    public function forceDelete(Nutgram $bot, string $id): void
    {
        $user = User::withTrashed()->find((int) $id);

        if (! $user) {
            $this->safeAlert($bot, '❌ المستخدم غير موجود.');
            return;
        }

        $userId = $user->id;
        $username = $user->username;
        $telegramId = $user->telegram_id;

        // ✅ إشعار المستخدم
        $this->notifyUserForceDeleted($bot, $user);

        // ✅ تسجيل
        try {
            \App\Models\AdminAction::log(
                adminId: $bot->userId(),
                action: \App\Models\AdminAction::ACTION_USER_FORCE_DELETE,
                targetType: User::class,
                targetId: $userId,
                changes: [
                    'username'    => $username,
                    'telegram_id' => $telegramId,
                ],
            );
        } catch (\Throwable $e) {
        }

        // ✅ إشعار القناة
        $this->notifyChannelForceDelete($bot, $username, $userId, $telegramId);

        // ✅ تنفيذ
        $user->forceDelete();

        $this->safeAlert($bot, '💥 تم الحذف النهائي.');

        $this->safeEdit(
            $bot,
            TrashedUsersScreen::listText(),
            TrashedUsersKeyboard::make(),
        );
    }

    // ============================================================
    //  📢 Notifications
    // ============================================================

    private function notifyChannelRestore(Nutgram $bot, User $user): void
    {
        try {
            $telegramLine = $this->notificationService->telegramInfoLine($user);

            $this->notificationService->notifyUsersChannel(
                $bot,
                implode("\n", [
                    '♻️ <b>تم استعادة مستخدم</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '👤 ' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8'),
                    '🆔 <code>' . $user->id . '</code>',
                    $telegramLine,
                    '',
                    '👮 بواسطة: <code>' . $bot->userId() . '</code>',
                ]),
            );
        } catch (\Throwable $e) {
            Log::warning('notifyChannelRestore failed', ['error' => $e->getMessage()]);
        }
    }

    private function notifyChannelForceDelete(
        Nutgram $bot,
        string $username,
        int $userId,
        ?int $telegramId,
    ): void {
        try {
            $telegramLine = $telegramId
                ? '<a href="tg://user?id=' . $telegramId . '"><code>' . $telegramId . '</code></a>'
                : '—';

            $this->notificationService->notifyUsersChannel(
                $bot,
                implode("\n", [
                    '💥 <b>حذف نهائي</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '👤 ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
                    '🆔 <code>' . $userId . '</code>',
                    '📱 Telegram: ' . $telegramLine,
                    '',
                    '⚠️ <b>لا يمكن التراجع.</b>',
                    '',
                    '👮 بواسطة: <code>' . $bot->userId() . '</code>',
                ]),
            );
        } catch (\Throwable $e) {
        }
    }

    private function notifyUserRestored(Nutgram $bot, User $user): void
    {
        if (! $user->telegram_id) return;

        try {
            $bot->sendMessage(
                text: implode("\n", [
                    '⚡ <b>VEXORA</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '♻️ <b>تم استعادة حسابك</b>',
                    '',
                    'استخدم /start للدخول.',
                ]),
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
        }
    }

    private function notifyUserForceDeleted(Nutgram $bot, User $user): void
    {
        if (! $user->telegram_id) return;

        try {
            $bot->sendMessage(
                text: implode("\n", [
                    '⚡ <b>VEXORA</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '💥 <b>تم حذف حسابك نهائياً</b>',
                    '',
                    '⚠️ لا يمكن التراجع عن هذا الإجراء.',
                ]),
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
        }
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
