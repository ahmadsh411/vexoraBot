<?php

namespace App\Telegram\Handlers\Admin\Users;

use App\Models\User;
use App\Services\NotificationService;
use App\Services\UserService;
use App\Telegram\Conversations\Admin\Users\EditUserConversation;
use App\Telegram\Keyboards\AdminsKeyboard\Users\UserDetailsKeyboard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class UserDetailsHandler
{
    public function __construct(
        private readonly UserService $userService,
        private readonly NotificationService $notificationService,
    ) {}

    // ============================================================
    //  👁️ عرض التفاصيل
    // ============================================================

    public function show(Nutgram $bot, string $id): void
    {
        // ✅ 1. إغلاق الكويري بإشعار سريع
        try {
            $bot->answerCallbackQuery(text: '⏳ جاري التحميل...');
        } catch (\Throwable $e) {
        }

        // ✅ 2. تعديل الرسالة الرئيسية لرسالة تحميل
        try {
            $bot->editMessageText(
                text: "⏳ <b>جاري جلب بيانات المستخدم...</b>\n\n" .
                    "🎮 يتم الاتصال بـ IChancy\n" .
                    "⚡ الرجاء الانتظار...",
                parse_mode: 'HTML',
                reply_markup: InlineKeyboardMarkup::make(),
            );
        } catch (\Throwable $e) {
            // تجاهل — يمكن الرسالة قديمة
        }

        // ✅ 3. جلب المستخدم
        $user = User::with('wallet')->find((int) $id);

        if (! $user) {
            $this->safeAlert($bot, '❌ المستخدم غير موجود.');
            return;
        }

        NewUsersHandler::markOneSeen($user);

        // ✅ 4. عرض التفاصيل
        $this->render($bot, $user);
    }

    // ============================================================
    //  ⚡ تفعيل / إيقاف
    // ============================================================

    public function toggle(Nutgram $bot, string $id): void
    {
        $user = User::find((int) $id);

        if (! $user) {
            $this->safeAlert($bot, '❌ المستخدم غير موجود.');
            return;
        }

        $wasActive = $user->is_active;

        if ($wasActive) {
            $this->userService->deactivate($user);
            $this->safeAlert($bot, '🔴 تم إيقاف المستخدم.');
            $this->notifyUserDeactivated($bot, $user);
        } else {
            $this->userService->activate($user);
            $this->safeAlert($bot, '🟢 تم تفعيل المستخدم.');
            $this->notifyUserActivated($bot, $user);
        }

        $user->refresh();

        // ✅ تسجيل الإجراء
        try {
            \App\Models\AdminAction::log(
                adminId: $bot->userId(),
                action: $wasActive
                    ? \App\Models\AdminAction::ACTION_USER_DEACTIVATE
                    : \App\Models\AdminAction::ACTION_USER_ACTIVATE,
                targetType: User::class,
                targetId: $user->id,
                changes: [
                    'is_active' => ['old' => $wasActive, 'new' => ! $wasActive],
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('AdminAction log failed', ['error' => $e->getMessage()]);
        }

        // ✅ إشعار القناة
        $this->notifyChannel(
            $bot,
            $user,
            icon: $wasActive ? '🔴' : '🟢',
            action: $wasActive ? 'إيقاف' : 'تفعيل',
        );

        $this->render($bot, $user);
    }

    // ============================================================
    //  ✏️ تعديل
    // ============================================================

    public function edit(Nutgram $bot, string $id): void
    {
        $user = User::find((int) $id);

        if (! $user) {
            $this->safeAlert($bot, '❌ المستخدم غير موجود.');
            return;
        }

        $this->safeAnswer($bot);

        $cacheKey = 'edit_user_target_' . $bot->userId() . '_' . $bot->chatId();
        Cache::put($cacheKey, $user->id, now()->addMinutes(10));

        EditUserConversation::begin(
            bot: $bot,
            userId: $bot->userId(),
            chatId: $bot->chatId(),
        );
    }

    // ============================================================
    //  🗑️ حذف
    // ============================================================

    public function delete(Nutgram $bot, string $id): void
    {
        $user = User::find((int) $id);

        if (! $user) {
            $this->safeAlert($bot, '❌ المستخدم غير موجود.');
            return;
        }

        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            implode("\n", [
                '⚠️ <b>تأكيد الحذف</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                'هل أنت متأكد من حذف:',
                '<code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
                '',
                '🔴 يمكن استعادته من "المحذوفون".',
                '',
                '🔔 سيصل إشعار للمستخدم.',
            ]),
            InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '✅ نعم، احذف',
                        callback_data: "admin.users.delete.confirm.{$user->id}",
                        style: ButtonStyle::DANGER,
                    ),
                    InlineKeyboardButton::make(
                        text: '❌ إلغاء',
                        callback_data: "admin.users.show.{$user->id}",
                    ),
                ),
        );
    }

    public function deleteConfirm(Nutgram $bot, string $id): void
    {
        $user = User::find((int) $id);

        if (! $user) {
            $this->safeAlert($bot, '❌ المستخدم غير موجود.');
            return;
        }

        // ✅ 1. إشعار المستخدم
        $this->notifyDeleted($bot, $user);

        // ✅ 2. تسجيل الإجراء
        try {
            \App\Models\AdminAction::log(
                adminId: $bot->userId(),
                action: \App\Models\AdminAction::ACTION_USER_DELETE,
                targetType: User::class,
                targetId: $user->id,
            );
        } catch (\Throwable $e) {
            Log::warning('AdminAction log failed', ['error' => $e->getMessage()]);
        }

        // ✅ 3. الحذف الناعم
        $this->userService->delete($user);

        $this->safeAlert($bot, '🗑️ تم حذف المستخدم.');

        // ✅ 4. العودة للقائمة
        app(UserListHandler::class)->list($bot);
    }

    // ============================================================
    //  🎨 Render
    // ============================================================

    private function render(Nutgram $bot, User $user): void
    {
        $safeName = htmlspecialchars($user->display_name, ENT_QUOTES, 'UTF-8');
        $safeUsername = htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8');

        $wallet = $user->wallet;
        $balanceSyp = $wallet ? number_format((float) $wallet->balance_nsp, 2) : '0.00';
        $balanceUsd = $wallet ? number_format((float) $wallet->balance_usd, 2) : '0.00';

        // ✅ رصيد IChancy
        $ichancyLine = $this->getIChancyBalanceLine($user);

        $this->safeEdit(
            $bot,
            implode("\n", [
                '⚡ <b>VEXORA — تفاصيل المستخدم</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👤 <b>الاسم:</b> ' . $safeName,
                '🆔 <b>المعرف:</b> <code>' . $user->id . '</code>',
                '📛 <b>اسم المستخدم:</b> <code>' . $safeUsername . '</code>',
                '📱 <b>Telegram ID:</b> <code>' . $user->telegram_id . '</code>',
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '💰 <b>الرصيد الداخلي:</b>',
                '├── 💰 NSP: <b>' . $balanceSyp . '</b>',
                '└── 💵 USD: <b>' . $balanceUsd . '</b>',
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                $ichancyLine,
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🟢 <b>الحالة:</b> ' . $user->status_label,
                '🎭 <b>الدور:</b> ' . $user->role_label,
                '🎯 <b>الإحالات:</b> <b>' . $user->referrals_count . '</b>',
            ]),
            UserDetailsKeyboard::make($user->id),
        );
    }

    // ============================================================
    //  🎮 سطر رصيد IChancy
    // ============================================================
    private function getIChancyBalanceLine(User $user): string
    {
        $account = $user->ichancyAccount;

        if (! $account) {
            return implode("\n", [
                '🎮 <b>رصيد IChancy:</b>',
                '└── ⚠️ <i>لم يتم الربط</i>',
                '',
            ]);
        }

        try {
            $balanceData = app(\App\Services\IChancy\IChancyAccountService::class)
                ->getBalanceForUser($user, 60);

            if (! $balanceData) {
                return implode("\n", [
                    '🎮 <b>رصيد IChancy:</b>',
                    '└── ⚠️ <i>تعذّر الجلب</i>',
                    '🆔 <b>Player ID:</b> <code>' . $account->ichancy_player_id . '</code>',
                    '',
                ]);
            }

            $balance  = (float) $balanceData['balance'];
            $currency = $balanceData['currency'] ?? 'NSP';
            $stale    = $balanceData['stale'] ?? false;

            $lines = [
                '🎮 <b>رصيد IChancy:</b>',
                '└── 💰 <b>' . number_format($balance, 2) . ' ' . $currency . '</b>',
                '🆔 <b>Player ID:</b> <code>' . $account->ichancy_player_id . '</code>',
            ];

            if ($stale) {
                $lines[] = '⚠️ <i>قيمة مخزّنة (آخر مزامنة)</i>';
            }

            $lines[] = '';

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            Log::warning('UserDetailsHandler: IChancy balance failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return implode("\n", [
                '🎮 <b>رصيد IChancy:</b>',
                '└── ⚠️ <i>تعذّر الجلب</i>',
                '',
            ]);
        }
    }

    // ============================================================
    //  📢 الإشعارات
    // ============================================================

    private function notifyChannel(
        Nutgram $bot,
        User $user,
        string $icon,
        string $action,
    ): void {
        try {
            $telegramLine = $this->notificationService->telegramInfoLine($user);

            $this->notificationService->notifyUsersChannel(
                $bot,
                implode("\n", [
                    $icon . ' <b>تم ' . $action . ' مستخدم</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '👤 <b>الاسم:</b> ' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8'),
                    '🆔 <b>المعرف:</b> <code>' . $user->id . '</code>',
                    $telegramLine,
                    '',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '👮 بواسطة: <code>' . $bot->userId() . '</code>',
                ]),
            );
        } catch (\Throwable $e) {
            Log::warning('notifyChannel failed', ['error' => $e->getMessage()]);
        }
    }

    private function notifyUserActivated(Nutgram $bot, User $user): void
    {
        if (! $user->telegram_id) return;

        try {
            $bot->sendMessage(
                text: implode("\n", [
                    '⚡ <b>VEXORA</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🟢 <b>تم تفعيل حسابك</b>',
                    '',
                    '📛 <b>اسم المستخدم:</b>',
                    '<code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
                    '',
                    'استخدم /start للدخول.',
                ]),
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            Log::warning('notifyUserActivated failed', ['error' => $e->getMessage()]);
        }
    }

    private function notifyUserDeactivated(Nutgram $bot, User $user): void
    {
        if (! $user->telegram_id) return;

        try {
            $bot->sendMessage(
                text: implode("\n", [
                    '⚡ <b>VEXORA</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🔴 <b>تم إيقاف حسابك</b>',
                    '',
                    'تواصل مع الإدارة للمزيد.',
                ]),
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            Log::warning('notifyUserDeactivated failed', ['error' => $e->getMessage()]);
        }
    }

    private function notifyDeleted(Nutgram $bot, User $user): void
    {
        if (! $user->telegram_id) return;

        try {
            $bot->sendMessage(
                text: implode("\n", [
                    '⚡ <b>VEXORA</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🗑️ <b>تم حذف حسابك</b>',
                    '',
                    'تواصل مع الإدارة إذا كنت تعتقد أن هذا خطأ.',
                ]),
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            Log::warning('notifyDeleted failed', ['error' => $e->getMessage()]);
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
