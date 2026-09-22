<?php

namespace App\Telegram\Conversations\Admin\System;

use App\Models\User;
use App\Services\AdminWelcomeService;
use App\Telegram\Handlers\Admin\System\AdminHandler;
use App\Services\NotificationService;
use App\Telegram\Conversations\BaseConversation;
use App\Telegram\Screens\Admin\System\AdminScreen;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class AddAdminConversation extends BaseConversation
{
    public function start(Nutgram $bot): void
    {
        $current = $this->getCurrentUser($bot);

        if (! $current || ! $current->isSuperAdmin()) {
            $this->keep(
                $bot,
                '👑 هذه الميزة للمشرف الأساسي فقط.',
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        $this->askTracked(
            $bot,
            AdminScreen::askAdminId(),
            parse_mode: 'HTML',
        );

        $this->next('receiveId');
    }

    public function receiveId(Nutgram $bot): void
    {
        $input = trim((string) ($bot->message()->text ?? ''));

        if ($input === '' || $input === '/cancel') {
            $this->keep(
                $bot,
                '❌ تم الإلغاء.',
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        // ✅ البحث
        $user = null;

        if (ctype_digit($input)) {
            $user = User::where('telegram_id', (int) $input)->first();
        } else {
            $username = ltrim($input, '@');

            $user = User::where('telegram_username', 'LIKE', "%{$username}%")
                ->orWhere('username', 'LIKE', "%{$username}%")
                ->first();
        }

        if (! $user) {
            // 🗑️ خطأ في الإدخال — يُحذف (يعيد المحاولة)
            $this->askTracked(
                $bot,
                "⚠️ لم يتم العثور على المستخدم.\n\n/cancel للإلغاء.",
            );
            return;
        }

        if ($user->is_admin) {
            $this->keep(
                $bot,
                "⚠️ هذا المستخدم أدمن بالفعل.\n\n🎭 " . $user->role_label,
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        if (! $user->is_active) {
            $this->keep(
                $bot,
                '⚠️ حساب المستخدم معطّل.',
                reply_markup: $this->backKeyboard(),
            );
            $this->endAndClean($bot);
            return;
        }

        // ============================================================
        //  ✅ ترقية المستخدم إلى أدمن
        // ============================================================
        $user->update([
            'is_admin'       => true,
            'is_super_admin' => false,
        ]);

        $user->refresh();

        $by = $this->getCurrentUser($bot);

        if ($by) {
            // ✅ 1. إشعار تغيير الدور (لمستخدم آخر — لا يُحذف)
            app(NotificationService::class)->notifyRoleChange(
                $bot,
                $user,
                'user',
                'admin',
                $by,
            );

            // ✅ 2. تسجيل العملية
            \App\Models\AdminAction::log(
                adminId: $by->id,
                action: \App\Models\AdminAction::ACTION_USER_PROMOTE,
                targetType: User::class,
                targetId: $user->id,
                changes: ['role' => ['old' => 'user', 'new' => 'admin']],
            );

            // ✅ 3. إرسال رسالة الترحيب (لمستخدم آخر — لا تُحذف)
            try {
                app(AdminWelcomeService::class)
                    ->sendWelcomeToNewAdmin($user, $by, 'admin');

                Log::info('Admin welcome sent to new admin', [
                    'user_id'  => $user->id,
                    'admin_id' => $by->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to send admin welcome', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }

            // ✅ 4. إشعار القناة العامة
            try {
                app(AdminHandler::class)->notifyGeneralRoleChange(
                    $bot,
                    $user,
                    'user',
                    'admin',
                    $by,
                );

                Log::info('General channel notified about new admin', [
                    'user_id' => $user->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to notify general channel', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // ✅ 4. تأكيد للـ super admin (يبقى + زر رجوع)
        $this->keep(
            $bot,
            implode("\n", [
                '✅ <b>تم إضافة أدمن</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👤 ' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8'),
                '🆔 <code>' . $user->telegram_id . '</code>',
                '',
                '🎭 🛡️ أدمن عادي',
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📨 <i>تم إرسال رسالة الترحيب مع قنوات الإشعارات له.</i>',
            ]),
            parse_mode: 'HTML',
            reply_markup: $this->backKeyboard(),
        );

        $this->endAndClean($bot);
    }

    private function getCurrentUser(Nutgram $bot): ?User
    {
        return User::where('telegram_id', $bot->userId())->first();
    }

    /**
     * 🔙 زر الرجوع الموحد
     */
    private function backKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: 'admin.system',
                ),
            );
    }
}
