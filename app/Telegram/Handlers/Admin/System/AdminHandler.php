<?php

namespace App\Telegram\Handlers\Admin\System;

use App\Models\User;
use App\Services\AdminChannelSyncService;
use App\Services\AdminWelcomeService;
use App\Services\NotificationService;
use App\Services\SubscriptionService;
use App\Telegram\Conversations\Admin\System\AddAdminConversation;
use App\Telegram\Keyboards\AdminsKeyboard\System\AdminKeyboard;
use App\Telegram\Screens\Admin\System\AdminScreen;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Command\BotCommand;
use SergiX44\Nutgram\Telegram\Types\Command\BotCommandScopeChat;

class AdminHandler
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    // ============================================================
    //  index
    // ============================================================

    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            AdminScreen::main(),
            AdminKeyboard::main(),
        );
    }

    // ============================================================
    //  show
    // ============================================================

    public function show(Nutgram $bot, string $id): void
    {
        $this->safeAnswer($bot);

        $admin = User::find((int) $id);

        if (! $admin || ! $admin->is_admin) {
            return;
        }

        $current = $this->getCurrentUser($bot);

        $this->safeEdit(
            $bot,
            AdminScreen::adminDetails($admin),
            AdminKeyboard::adminDetails($admin, $current?->isSuperAdmin() ?? false),
        );
    }

    // ============================================================
    //  create
    // ============================================================

    public function create(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        AddAdminConversation::begin($bot);
    }

    // ============================================================
    //  promote (ترقية/تخفيض)
    // ============================================================

    public function promote(Nutgram $bot, string $id): void
    {
        $this->safeAnswer($bot);

        $admin = User::find((int) $id);

        if (! $admin) {
            return;
        }

        $by = $this->getCurrentUser($bot);

        if (! $by) {
            return;
        }

        $currentRole = $this->getRole($admin);
        $newRole = $currentRole === 'super_admin' ? 'admin' : 'super_admin';

        $admin->update([
            'is_admin'       => true,
            'is_super_admin' => $newRole === 'super_admin',
        ]);

        $admin->refresh();

        // ============================================================
        //  👑 الترقية → إرسال رسالة ترحيب جديدة
        //  🔻 التخفيض (super→admin) → لا يفعل شيئاً
        // ============================================================
        if ($this->isPromotion($currentRole, $newRole)) {
            // ✅ ترقية (user→admin أو admin→super) → إرسال رسالة
            try {
                app(AdminWelcomeService::class)
                    ->sendWelcomeToNewAdmin($admin, $by, $newRole);
            } catch (\Throwable $e) {
                Log::warning('Failed to send admin welcome', [
                    'user_id' => $admin->id,
                    'role'    => $newRole,
                    'error'   => $e->getMessage(),
                ]);
            }
        } else {
            // ✅ تخفيض super_admin → admin
            // لا يفعل شيئاً — يبقى في القنوات، والرسالة تبقى
            Log::info('Admin demoted super→admin — no changes to channels or message', [
                'user_id' => $admin->id,
            ]);
        }

        // ✅ إشعار للمستخدم
        app(NotificationService::class)->notifyRoleChange(
            $bot,
            $admin,
            $currentRole,
            $newRole,
            $by,
        );

        // ✅ إشعار قناة General
        $this->notifyGeneralRoleChange(
            $bot,
            $admin,
            $currentRole,
            $newRole,
            $by,
        );

        // ✅ تسجيل
        \App\Models\AdminAction::log(
            adminId: $by->id,
            action: $newRole === 'super_admin'
                ? \App\Models\AdminAction::ACTION_USER_PROMOTE
                : \App\Models\AdminAction::ACTION_USER_DEMOTE,
            targetType: User::class,
            targetId: $admin->id,
            changes: [
                'role' => ['old' => $currentRole, 'new' => $newRole],
            ],
        );

        $this->show($bot, $id);
    }

    // ============================================================
    //  delete — Confirm
    // ============================================================

    public function confirmDelete(Nutgram $bot, string $id): void
    {
        $this->safeAnswer($bot);

        $admin = User::find((int) $id);

        if (! $admin || ! $admin->is_admin) {
            return;
        }

        // ✅ لا يمكن حذف آخر سوبر
        if ($admin->is_super_admin) {
            $superCount = User::where('is_admin', true)
                ->where('is_super_admin', true)
                ->count();

            if ($superCount <= 1) {
                $this->safeAlert($bot, '⚠️ لا يمكن حذف آخر مشرف أساسي.');
                return;
            }
        }

        // ✅ لا يمكن حذف نفسك
        $current = $this->getCurrentUser($bot);

        if ($current && $current->id === $admin->id) {
            $this->safeAlert($bot, '⚠️ لا يمكنك حذف حسابك.');
            return;
        }

        $this->safeEdit(
            $bot,
            AdminScreen::confirmDelete($admin),
            AdminKeyboard::confirmDelete($admin),
        );
    }

    // ============================================================
    //  delete — Execute (مع طرد من القنوات)
    // ============================================================

    public function delete(Nutgram $bot, string $id): void
    {
        $this->safeAnswer($bot);

        $admin = User::find((int) $id);

        if (! $admin || ! $admin->is_admin) {
            return;
        }

        if ($admin->is_super_admin) {
            $superCount = User::where('is_admin', true)
                ->where('is_super_admin', true)
                ->count();

            if ($superCount <= 1) {
                $this->safeAlert($bot, '⚠️ لا يمكن حذف آخر مشرف أساسي.');
                return;
            }
        }

        $current = $this->getCurrentUser($bot);

        if ($current && $current->id === $admin->id) {
            $this->safeAlert($bot, '⚠️ لا يمكنك حذف حسابك.');
            return;
        }

        $oldRole = $this->getRole($admin);

        // ============================================================
        //  🚪 1. طرد المستخدم من قنوات الإشعارات (قبل التخفيض)
        // ============================================================
        try {
            app(AdminChannelSyncService::class)
                ->removeFromAllChannels($admin);

            Log::info('Removed admin from all channels', [
                'user_id'  => $admin->id,
                'old_role' => $oldRole,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to remove admin from channels', [
                'user_id' => $admin->id,
                'error'   => $e->getMessage(),
            ]);
        }

        // ============================================================
        //  🗑️ 2. حذف رسالة الترحيب من محادثته
        // ============================================================
        try {
            app(AdminWelcomeService::class)->deleteWelcome($admin);

            Log::info('Deleted admin welcome message', [
                'user_id' => $admin->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to delete admin welcome', [
                'user_id' => $admin->id,
                'error'   => $e->getMessage(),
            ]);
        }

        // ============================================================
        //  ☰ إعادة أوامر المستخدم العادي
        // ============================================================
        if ($admin->telegram_id) {
            try {
                $bot->setMyCommands(
                    commands: [
                        new BotCommand(
                            command: 'start',
                            description: 'القائمة',
                        ),
                    ],
                    scope: new BotCommandScopeChat(
                        chat_id: $admin->telegram_id,
                    ),
                );

                Log::info('Reset user commands', [
                    'user_id' => $admin->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to reset user commands', [
                    'user_id' => $admin->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // ============================================================
        //  🔻 3. تخفيض الدور
        // ============================================================
        $admin->update([
            'is_admin'       => false,
            'is_super_admin' => false,
        ]);

        $admin->refresh();

        // ============================================================
        //  📢 4. إشعارات
        // ============================================================
        if ($current) {
            // ✅ إشعار للمستخدم
            app(NotificationService::class)->notifyRoleChange(
                $bot,
                $admin,
                $oldRole,
                'user',
                $current,
            );

            // ✅ إشعار قناة General
            $this->notifyGeneralRoleChange(
                $bot,
                $admin,
                $oldRole,
                'user',
                $current,
            );

            // ✅ تسجيل
            \App\Models\AdminAction::log(
                adminId: $current->id,
                action: 'user.demote_to_user',
                targetType: User::class,
                targetId: $admin->id,
                changes: [
                    'role' => ['old' => $oldRole, 'new' => 'user'],
                ],
            );
        }

        $this->index($bot);
    }

    // ============================================================
    //  📢 إشعار قناة General — تغيير الدور
    // ============================================================

    public function notifyGeneralRoleChange(
        Nutgram $bot,
        User $user,
        string $fromRole,
        string $toRole,
        User $by,
    ): void {
        try {
            $isPromotion = $this->isPromotion($fromRole, $toRole);

            $icon  = $isPromotion ? '🎉' : '⚠️';
            $title = $isPromotion ? 'ترقية مستخدم' : 'تخفيض مستخدم';

            $text = implode("\n", [
                $icon . ' <b>' . $title . '</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👤 <b>المستخدم:</b>',
                '└── <code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
                '└── 🆔 <code>#' . $user->id . '</code>',
                '',
                '📌 <b>التغيير:</b>',
                '├── من: ' . $this->roleLabel($fromRole),
                '└── إلى: ' . $this->roleLabel($toRole),
                '',
                '👮 <b>بواسطة:</b> <code>' . htmlspecialchars($by->username, ENT_QUOTES, 'UTF-8') . '</code>',
                '',
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            Log::info('🔔 notifyGeneralRoleChange: START', [
                'user_id'   => $user->id,
                'username'  => $user->username,
                'from_role' => $fromRole,
                'to_role'   => $toRole,
                'is_promo'  => $isPromotion,
                'text_len'  => strlen($text),
                'channel_id' => config('services.telegram.general_channel_id'),
            ]);

            $result = $this->notificationService->notifyGeneralChannel($bot, $text);

            Log::info('🔔 notifyGeneralRoleChange: RESULT', [
                'user_id' => $user->id,
                'success' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('🔔 notifyGeneralRoleChange: FAILED', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
        }
    }

    private function isPromotion(string $from, string $to): bool
    {
        $levels = [
            'user'        => 0,
            'admin'       => 1,
            'super_admin' => 2,
        ];

        return ($levels[$to] ?? 0) > ($levels[$from] ?? 0);
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'super_admin' => '👑 مشرف أساسي',
            'admin'       => '🛡️ أدمن عادي',
            'user'        => '👤 مستخدم',
            default       => '❓',
        };
    }


    // ============================================================
    //  ✅ تأكيد الاشتراك (Admin)
    // ============================================================
    public function joinConfirm(Nutgram $bot, string $id): void
    {
        $this->safeAnswer($bot);

        $admin = User::find((int) $id);

        if (! $admin || ! $admin->is_admin) {
            return;
        }

        if (! $admin->telegram_id) {
            try {
                $bot->sendMessage(
                    text: '⚠️ لا يمكن التحقق — لم يتم ربط حسابك بعد.',
                    chat_id: $bot->chatId(),
                );
            } catch (\Throwable $e) {
            }
            return;
        }

        // ✅ 1. احذف رسالة التحقق القديمة أولاً
        $this->deleteOldJoinMessage($admin);

        try {
            $service = app(SubscriptionService::class);
            $missing = $service->getMissingAdminChannels($admin->telegram_id);

            // ─── إذا نقصت قناة ───
            if (! empty($missing)) {
                $lines = [
                    '⚠️ <b>لم تشترك في كل القنوات</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '📢 <b>يجب الاشتراك في القنوات التالية:</b>',
                    '',
                ];

                foreach ($missing as $key => $info) {
                    $lines[] = '❌ ' . $info['name'];
                }

                $lines[] = '';
                $lines[] = '━━━━━━━━━━━━━━━━━━';
                $lines[] = '';
                $lines[] = '💡 <i>بعد الاشتراك، اضغط "✅ تم الاشتراك" مرة أخرى.</i>';

                $keyboard = \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup::make();

                foreach ($missing as $key => $info) {
                    if (! empty($info['link'])) {
                        $keyboard->addRow(
                            \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                                text: '📢 اشترك: ' . $info['name'],
                                url: $info['link'],
                            ),
                        );
                    }
                }

                $keyboard->addRow(
                    \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                        text: '✅ تم الاشتراك',
                        callback_data: 'admin.join.confirm.' . $admin->id,
                        style: \SergiX44\Nutgram\Telegram\Properties\ButtonStyle::SUCCESS,
                    ),
                );

                try {
                    // ✅ 2. احفظ message_id الجديدة
                    $sentMessage = $bot->sendMessage(
                        text: implode("\n", $lines),
                        chat_id: $bot->chatId(),
                        parse_mode: 'HTML',
                        reply_markup: $keyboard,
                        disable_web_page_preview: true,
                    );

                    if ($sentMessage && $sentMessage->message_id) {
                        $admin->update([
                            'admin_welcome_message_id' => $sentMessage->message_id,
                        ]);
                    }
                } catch (\Throwable $e) {
                }

                Log::info('Admin: joinConfirm — missing channels', [
                    'admin_id' => $admin->id,
                    'missing'  => array_keys($missing),
                ]);

                return;
            }

            // ─── ✅ مشترك في الكل ───
            try {
                $bot->sendMessage(
                    text: implode("\n", [
                        '🎉 <b>شكراً لك!</b>',
                        '━━━━━━━━━━━━━━━━━━',
                        '',
                        '✅ <b>تم تأكيد اشتراكك في جميع القنوات</b>',
                        '',
                        '📬 ستصلك الإشعارات تلقائياً.',
                        '',
                        '💚 <i>نحن سعداء بوجودك في الفريق!</i>',
                    ]),
                    chat_id: $bot->chatId(),
                    parse_mode: 'HTML',
                );
            } catch (\Throwable $e) {
            }

            Log::info('Admin: joinConfirm — success', [
                'admin_id' => $admin->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Admin: joinConfirm failed', [
                'admin_id' => $admin->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  🗑️ حذف رسالة التحقق القديمة
    // ============================================================
    private function deleteOldJoinMessage(User $admin): void
    {
        if (! $admin->telegram_id || empty($admin->admin_welcome_message_id)) {
            return;
        }

        try {
            $bot = app(\SergiX44\Nutgram\Nutgram::class);

            $bot->deleteMessage(
                chat_id: $admin->telegram_id,
                message_id: (int) $admin->admin_welcome_message_id,
            );

            $admin->update(['admin_welcome_message_id' => null]);

            Log::info('Admin: old join message deleted', [
                'admin_id' => $admin->id,
            ]);
        } catch (\Throwable $e) {
            // تجاهل
            $admin->update(['admin_welcome_message_id' => null]);
        }
    }
    // ============================================================
    //  Helpers
    // ============================================================

    private function getRole(User $user): string
    {
        if (! $user->is_admin) return 'user';
        return $user->is_super_admin ? 'super_admin' : 'admin';
    }

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
