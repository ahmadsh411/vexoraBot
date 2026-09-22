<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Command\BotCommand;
use SergiX44\Nutgram\Telegram\Types\Command\BotCommandScopeChat;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class AdminWelcomeService
{
    /**
     * روابط قنوات الإشعارات
     */
    public const INVITE_LINKS = [
        'transactions' => 'https://t.me/+awdfBCBjSuowNjg0',
        'general'      => 'https://t.me/+oLcV6dPMV3kwMDFk',
        'users'        => 'https://t.me/+2i-rmMlDMrQxZGFk',
    ];

    /**
     * تسميات القنوات
     */
    public const CHANNEL_LABELS = [
        'transactions' => '💰 التحويلات',
        'general'      => '📢 العامة',
        'users'        => '👥 المستخدمين',
    ];

    // ============================================================
    //  👑 إرسال رسالة الترحيب للأدمن الجديد
    // ============================================================
    public function sendWelcomeToNewAdmin(User $user, ?User $promotedBy = null, string $role = 'admin'): bool
    {
        if (! $user->telegram_id) {
            Log::warning('AdminWelcomeService: no telegram_id', [
                'user_id' => $user->id,
            ]);
            return false;
        }

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);

        // ─── الدور ───
        $roleLabel = match ($role) {
            'super_admin' => '👑 <b>مشرف أساسي</b>',
            'admin'       => '🛡️ <b>أدمن</b>',
            default       => '👤 مستخدم',
        };

        // ─── الرسالة ───
        $text = implode("\n", [
            '👑 <b>تم ترقيتك إلى أدمن</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            "✅ دورك الجديد: {$roleLabel}",
            '',
            $promotedBy
                ? '👤 <b>قام بترقيتك:</b> <code>' . htmlspecialchars($promotedBy->username, ENT_QUOTES, 'UTF-8') . '</code>'
                : '',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '⚠️ <b>خطوة مطلوبة منك</b>',
            '',
            '📢 <b>يجب أن تنضم إلى قنوات الإشعارات</b>',
            '',
            'لتستقبل:',
            '• 💰 إشعارات العمليات المالية',
            '• 📢 الإشعارات العامة',
            '• 👥 إشعارات المستخدمين الجدد',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👇 <b>اضغط على الأزرار أدناه للانضمام:</b>',
            '',
            '🔒 <i>ملاحظة: لن تستطيع الكتابة — فقط القراءة.</i>',
            '⏱ <i>سيستغرق الأمر أقل من دقيقة.</i>',
        ]);

        // ─── الأزرار ───
        $keyboard = InlineKeyboardMarkup::make();

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: self::CHANNEL_LABELS['transactions'],
                url: self::INVITE_LINKS['transactions'],
            ),
            InlineKeyboardButton::make(
                text: self::CHANNEL_LABELS['general'],
                url: self::INVITE_LINKS['general'],
            ),
            InlineKeyboardButton::make(
                text: self::CHANNEL_LABELS['users'],
                url: self::INVITE_LINKS['users'],
            ),
        );

        // ✅ زر التحقق
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '✅ تم الاشتراك',
                callback_data: 'admin.join.confirm.' . $user->id,
                style: \SergiX44\Nutgram\Telegram\Properties\ButtonStyle::SUCCESS,
            ),
        );

        // ─── الإرسال ───
        try {
            // ✅ احذف الرسالة القديمة أولاً
            $this->deleteWelcome($user);

            $sentMessage = $bot->sendMessage(
                text: $text,
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
                disable_web_page_preview: true,
            );

            // ✅ احفظ message_id
            if ($sentMessage && $sentMessage->message_id) {
                $user->update([
                    'admin_welcome_message_id' => $sentMessage->message_id,
                ]);
            }

            // ═══════════════════════════════════════════════════════
            //  ☰ تحديث أوامر البوت (القائمة الجانبية)
            // ═══════════════════════════════════════════════════════
            try {
                $bot->setMyCommands(
                    commands: [
                        new BotCommand(
                            command: 'admin',
                            description: 'لوحة التحكم',
                        ),
                    ],
                    scope: new BotCommandScopeChat(
                        chat_id: $user->telegram_id,
                    ),
                );

                Log::info('AdminWelcomeService: admin commands set', [
                    'user_id' => $user->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('AdminWelcomeService: failed to set commands', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }

            Log::info('AdminWelcomeService: welcome sent', [
                'user_id'     => $user->id,
                'message_id'  => $sentMessage?->message_id,
                'promoted_by' => $promotedBy?->id,
                'role'        => $role,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('AdminWelcomeService: failed to send', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ============================================================
    //  🗑️ حذف رسالة الترحيب
    // ============================================================
    public function deleteWelcome(User $user): bool
    {
        if (! $user->telegram_id) {
            return false;
        }

        if (empty($user->admin_welcome_message_id)) {
            Log::debug('AdminWelcomeService: no welcome message to delete', [
                'user_id' => $user->id,
            ]);
            return false;
        }

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);

        try {
            $bot->deleteMessage(
                chat_id: $user->telegram_id,
                message_id: (int) $user->admin_welcome_message_id,
            );

            Log::info('AdminWelcomeService: welcome message deleted', [
                'user_id'    => $user->id,
                'message_id' => $user->admin_welcome_message_id,
            ]);

            $user->update(['admin_welcome_message_id' => null]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('AdminWelcomeService: failed to delete welcome', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            // صفّر حتى لا يبقى عالقًا
            $user->update(['admin_welcome_message_id' => null]);

            return false;
        }
    }

    // ============================================================
    //  📨 إعادة إرسال الروابط
    // ============================================================
    public function resendInviteLinks(User $user): bool
    {
        $role = $user->is_super_admin ? 'super_admin' : 'admin';

        return $this->sendWelcomeToNewAdmin($user, null, $role);
    }
}
