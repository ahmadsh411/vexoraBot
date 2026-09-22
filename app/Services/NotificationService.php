<?php

namespace App\Services;

use App\Models\DepositMethod;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class NotificationService
{
    // ============================================================
    //  🎯 القنوات الثلاث
    // ============================================================

    /**
     * قناة المستخدمين الجدد.
     */
    public function notifyUsersChannel(Nutgram $bot, string $text): bool
    {
        return $this->sendToChannel(
            $bot,
            config('services.telegram.users_channel_id'),
            $text,
        );
    }

    /**
     * قناة المالية (إيداع، سحب، تحويلات).
     */
    public function notifyTransactionsChannel(Nutgram $bot, string $text): bool
    {
        return $this->sendToChannel(
            $bot,
            config('services.telegram.transactions_channel_id'),
            $text,
        );
    }

    /**
     * قناة عامة (النظام، الإعدادات).
     */
    public function notifyGeneralChannel(Nutgram $bot, string $text): bool
    {
        return $this->sendToChannel(
            $bot,
            config('services.telegram.general_channel_id'),
            $text,
        );
    }

    /**
     * إرسال إلى قناة محددة.
     */
    protected function sendToChannel(Nutgram $bot, ?string $channelId, string $text): bool
    {
        if (empty($channelId)) {
            Log::warning('NotificationService: Channel ID is empty');
            return false;
        }

        try {
            $bot->sendMessage(
                text: $text,
                chat_id: $channelId,
                parse_mode: 'HTML',
                disable_web_page_preview: true,
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('NotificationService: Failed to send to channel', [
                'channel_id' => $channelId,
                'error'      => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ============================================================
    //  📢 إشعارات الأدمن (مباشر)
    // ============================================================

    public function notifyAdmins(Nutgram $bot, string $text): void
    {
        $admins = User::where('is_admin', true)
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            $this->send($bot, $admin, $text);
        }
    }

    public function notifySuperAdmins(Nutgram $bot, string $text): void
    {
        $admins = User::where('is_admin', true)
            ->where('is_super_admin', true)
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            $this->send($bot, $admin, $text);
        }
    }

    public function send(Nutgram $bot, User $user, string $text): bool
    {
        if ($user->telegram_id === null) {
            return false;
        }

        try {
            $bot->sendMessage(
                text: $text,
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('NotificationService: send failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ============================================================
    //  🆕 إشعار قناة المستخدمين (تسجيل جديد)
    // ============================================================

    public function sendWelcomeToChannel(Nutgram $bot, User $user, string $plainPassword): void
    {
        $botName = \App\Models\Setting::get('bot_name_prefix', 'VEXORA');
        $telegramLink = '<a href="tg://user?id=' . $user->telegram_id . '">'
            . '<code>' . $user->telegram_id . '</code></a>';

        $text = implode("\n", [
            '🆕 <b>مستخدم جديد في ' . $botName . '</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👤 <b>الاسم:</b> ' . htmlspecialchars($user->full_name, ENT_QUOTES, 'UTF-8'),
            '',
            '📛 <b>اسم المستخدم:</b>',
            '└── <code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
            '',
            '🔑 <b>كلمة المرور:</b>',
            '└── <code>' . htmlspecialchars($plainPassword, ENT_QUOTES, 'UTF-8') . '</code>',
            '',
            '🆔 <b>معرف الحساب:</b>',
            '└── <code>#' . $user->id . '</code>',
            '',
            '📱 <b>Telegram ID:</b>',
            '└── ' . $telegramLink,
            '',
            '📅 <b>تاريخ التسجيل:</b>',
            '└── ' . now()->format('Y-m-d H:i:s'),
        ]);

        $this->notifyUsersChannel($bot, $text);
    }

    // ============================================================
    //  🎯 إشعار ترحيب للمستخدم
    // ============================================================

    public function sendWelcomeToUser(Nutgram $bot, User $user, string $plainPassword): void
    {
        $botName = \App\Models\Setting::get('bot_name_prefix', 'VEXORA');
        $botUsername = config('services.telegram.bot_username', 'VexoraBot');
        $botLink = "https://t.me/{$botUsername}";

        $text = implode("\n", [
            '🎉 <b>مرحباً بك في ' . $botName . '</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '✅ <b>تم إنشاء حسابك بنجاح!</b>',
            '',
            '📋 <b>معلومات حسابك:</b>',
            '├── 👤 <b>اسم المستخدم:</b>',
            '│   <code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
            '│',
            '├── 🔑 <b>كلمة المرور:</b>',
            '│   <code>' . htmlspecialchars($plainPassword, ENT_QUOTES, 'UTF-8') . '</code>',
            '│',
            '├── 📱 <b>Telegram ID:</b>',
            '│   <code>' . $user->telegram_id . '</code>',
            '│',
            '└── 🆔 <b>معرف الحساب:</b>',
            '    <code>#' . $user->id . '</code>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '⚠️ <b>مهم جدًا:</b>',
            '• احتفظ بهذه المعلومات في مكان آمن',
            '• لا تشارك كلمة المرور مع أي شخص',
            '',
            '🔗 <b>رابط البوت:</b>',
            $botLink,
            '',
            '💡 <b>للدخول مستقبلاً:</b>',
            'أرسل /start',
        ]);

        $this->send($bot, $user, $text);
    }

    // ============================================================
    //  👤 Helpers — Telegram Info Line
    // ============================================================

    public function telegramIdLine(User $user): string
    {
        if (empty($user->telegram_id)) {
            return '<code>—</code> (غير متوفر)';
        }

        $id   = $user->telegram_id;
        $link = "tg://user?id={$id}";

        return '<a href="' . $link . '"><code>' . $id . '</code></a>';
    }

    public function telegramInfoLine(User $user): string
    {
        if (empty($user->telegram_id)) {
            return '📱 <b>Telegram:</b> — (غير متوفر)';
        }

        $id       = $user->telegram_id;
        $username = $user->telegram_username;
        $link     = "tg://user?id={$id}";

        $line = '📱 <b>Telegram:</b> <a href="' . $link . '">';

        if ($username) {
            $line .= '@' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        } else {
            $line .= 'مستخدم';
        }

        $line .= '</a> <code>(' . $id . ')</code>';

        return $line;
    }

    // ============================================================
    //  📢 إشعارات عامة (للقناة العامة + Broadcast)
    // ============================================================

    public function notifyMaintenance(string $message): void
    {
        $text = implode("\n", [
            '🔧 <b>إشعار صيانة</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            $message,
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🙏 نعتذر عن الإزعاج',
        ]);

        // ✅ إرسال للقناة العامة
        try {
            $bot = app(Nutgram::class);
            $this->notifyGeneralChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify general channel about maintenance', [
                'error' => $e->getMessage(),
            ]);
        }

        // ✅ إرسال Broadcast للمستخدمين
        \App\Jobs\BroadcastMessageJob::dispatch('all', $text, 0);
    }

    public function notifyMaintenanceEnded(): void
    {
        $text = implode("\n", [
            '✅ <b>انتهت الصيانة</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎉 البوت يعمل الآن بشكل طبيعي.',
            '',
            '💡 استخدم /start للبدء.',
        ]);

        // ✅ إرسال للقناة العامة
        try {
            $bot = app(Nutgram::class);
            $this->notifyGeneralChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify general channel about maintenance ended', [
                'error' => $e->getMessage(),
            ]);
        }

        // ✅ إرسال Broadcast للمستخدمين
        \App\Jobs\BroadcastMessageJob::dispatch('all', $text, 0);
    }

    // ============================================================
    //  🎭 إشعار تغيير الدور
    // ============================================================

    public function notifyRoleChange(
        Nutgram $bot,
        User $user,
        string $fromRole,
        string $toRole,
        User $by,
    ): void {
        $isPromotion = $this->isPromotion($fromRole, $toRole);

        $icon  = $isPromotion ? '🎉' : '⚠️';
        $title = $isPromotion ? 'تم ترقيتك' : 'تم تخفيضك';

        // ✅ إشعار للمستخدم
        $this->send($bot, $user, implode("\n", [
            $icon . ' <b>' . $title . '</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📌 <b>التغيير:</b>',
            '├── من: ' . $this->roleLabel($fromRole),
            '└── إلى: ' . $this->roleLabel($toRole),
            '',
            '👮 <b>بواسطة:</b> <code>' . $by->username . '</code>',
            '',
            '📅 ' . now()->format('Y-m-d H:i'),
        ]));
    }

    private function isPromotion(string $from, string $to): bool
    {
        $levels = [
            User::ROLE_USER        => 0,
            User::ROLE_ADMIN       => 1,
            User::ROLE_SUPER_ADMIN => 2,
        ];

        return ($levels[$to] ?? 0) > ($levels[$from] ?? 0);
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            User::ROLE_SUPER_ADMIN => '👑 مشرف أساسي',
            User::ROLE_ADMIN       => '🛡️ أدمن عادي',
            User::ROLE_USER        => '👤 مستخدم',
            default                => '❓',
        };
    }

    // ============================================================
    //  📢 إشعارات جماعية (عبر Job)
    // ============================================================

    public function notifyAllUsers(string $text): int
    {
        \App\Jobs\BroadcastMessageJob::dispatch('all', $text, 0);

        return User::whereNotNull('telegram_id')->count();
    }

    // ============================================================
    //  💰 إشعارات تغيير طرق الإيداع — للقناة المالية
    // ============================================================

    /**
     * إشعار قناة Transactions عن تغيير طريقة إيداع.
     *
     * @param  string  $action  create|update|delete|activate|deactivate
     */
    public function notifyDepositMethodChange(
        Nutgram $bot,
        string $action,
        DepositMethod|array $method,
        ?int $adminId = null,
        ?array $changes = null,
    ): void {
        $method = is_array($method)
            ? (object) $method
            : $method;

        $admin = $adminId ? User::find($adminId) : null;
        $adminName = $admin
            ? '<code>' . htmlspecialchars($admin->username, ENT_QUOTES, 'UTF-8') . '</code>'
            : '<code>—</code>';

        $icon = match ($action) {
            'create'     => '🆕',
            'update'     => '✏️',
            'delete'     => '🗑️',
            'activate'   => '🟢',
            'deactivate' => '🔴',
            default      => 'ℹ️',
        };

        $title = match ($action) {
            'create'     => 'إضافة طريقة إيداع جديدة',
            'update'     => 'تعديل طريقة إيداع',
            'delete'     => 'حذف طريقة إيداع',
            'activate'   => 'تفعيل طريقة إيداع',
            'deactivate' => 'تعطيل طريقة إيداع',
            default      => 'تحديث طريقة إيداع',
        };

        $lines = [
            $icon . ' <b>' . $title . '</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💳 <b>الاسم:</b> ' . htmlspecialchars((string) ($method->name ?? '—'), ENT_QUOTES, 'UTF-8'),
            '🔖 <b>الكود:</b> <code>' . htmlspecialchars((string) ($method->code ?? '—'), ENT_QUOTES, 'UTF-8') . '</code>',
            '💱 <b>العملة:</b> ' . htmlspecialchars((string) ($method->currency ?? '—'), ENT_QUOTES, 'UTF-8'),
        ];

        if (! empty($method->min_amount) || ! empty($method->max_amount)) {
            $lines[] = '📊 <b>الحدود:</b> '
                . number_format((float) ($method->min_amount ?? 0), 2)
                . ' → '
                . number_format((float) ($method->max_amount ?? 0), 2);
        }

        if ($changes && is_array($changes)) {
            $lines[] = '';
            $lines[] = '📝 <b>التغييرات:</b>';
            foreach ($changes as $field => $change) {
                $old = is_array($change) ? ($change['old'] ?? '—') : '—';
                $new = is_array($change) ? ($change['new'] ?? '—') : '—';

                if (is_bool($old)) {
                    $old = $old ? '✅' : '❌';
                }
                if (is_bool($new)) {
                    $new = $new ? '✅' : '❌';
                }

                $lines[] = '├── <b>' . htmlspecialchars((string) $field, ENT_QUOTES, 'UTF-8') . ':</b>';
                $lines[] = '│   <s>' . htmlspecialchars((string) $old, ENT_QUOTES, 'UTF-8') . '</s>';
                $lines[] = '│   → <b>' . htmlspecialchars((string) $new, ENT_QUOTES, 'UTF-8') . '</b>';
            }
        }

        $lines[] = '';
        $lines[] = '👮 <b>بواسطة:</b> ' . $adminName;
        $lines[] = '📅 ' . now()->format('Y-m-d H:i');

        $this->notifyTransactionsChannel($bot, implode("\n", $lines));
    }

    /**
     * إشعار كل المستخدمين عن تغيير طريقة إيداع (Broadcast).
     */
    public function notifyAllUsersDepositMethodChange(
        Nutgram $bot,
        string $action,
        DepositMethod|array $method,
        ?array $changes = null,
    ): int {
        $method = is_array($method)
            ? (object) $method
            : $method;

        $icon = match ($action) {
            'create'     => '🆕',
            'update'     => '✏️',
            'delete'     => '🗑️',
            'activate'   => '🟢',
            'deactivate' => '🔴',
            default      => 'ℹ️',
        };

        $title = match ($action) {
            'create'     => 'طريقة إيداع جديدة متاحة',
            'update'     => 'تحديث طريقة إيداع',
            'delete'     => 'تم حذف طريقة إيداع',
            'activate'   => 'تم تفعيل طريقة إيداع',
            'deactivate' => 'تم تعطيل طريقة إيداع',
            default      => 'تحديث طرق الإيداع',
        };

        $lines = [
            $icon . ' <b>' . $title . '</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💳 <b>الاسم:</b> ' . htmlspecialchars((string) ($method->name ?? '—'), ENT_QUOTES, 'UTF-8'),
            '💱 <b>العملة:</b> ' . htmlspecialchars((string) ($method->currency ?? '—'), ENT_QUOTES, 'UTF-8'),
        ];

        if (! empty($method->min_amount) || ! empty($method->max_amount)) {
            $lines[] = '📊 <b>الحدود:</b> '
                . number_format((float) ($method->min_amount ?? 0), 2)
                . ' → '
                . number_format((float) ($method->max_amount ?? 0), 2);
        }

        if ($changes && is_array($changes)) {
            $lines[] = '';
            $lines[] = '📝 <b>التغييرات:</b>';
            foreach ($changes as $field => $change) {
                $old = is_array($change) ? ($change['old'] ?? '—') : '—';
                $new = is_array($change) ? ($change['new'] ?? '—') : '—';

                if (is_bool($old)) {
                    $old = $old ? '✅' : '❌';
                }
                if (is_bool($new)) {
                    $new = $new ? '✅' : '❌';
                }

                $lines[] = '├── <b>' . htmlspecialchars((string) $field, ENT_QUOTES, 'UTF-8') . ':</b>';
                $lines[] = '│   <s>' . htmlspecialchars((string) $old, ENT_QUOTES, 'UTF-8') . '</s>';
                $lines[] = '│   → <b>' . htmlspecialchars((string) $new, ENT_QUOTES, 'UTF-8') . '</b>';
            }
        }

        $lines[] = '';
        $lines[] = '💡 استخدم /start لعرض الطرق.';

        $text = implode("\n", $lines);

        return $this->notifyAllUsers($text);
    }
}
