<?php

namespace App\Telegram\Commands;

use App\Models\Setting;
use App\Models\User;
use App\Services\GiftCodeService;
use App\Telegram\Conversations\RegisterConversation;
use App\Telegram\Handlers\User\UserDashboardHandler;
use App\Telegram\Screens\Admin\DashboardScreen;
use App\Telegram\Keyboards\AdminsKeyboard\DashboardKeyboard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;
use App\Services\SubscriptionService;

class StartCommand extends Command
{
    protected string $command = 'start';
    protected ?string $description = 'Open VEXORA';

    // ============================================================
    //  🚀 نقطة الدخول
    // ============================================================
    public function handle(Nutgram $bot, ?string $payload = null): void
    {
        $telegramUser = $bot->user();

        if ($telegramUser === null) {
            $bot->sendMessage(text: '❌ تعذر التعرف على حساب Telegram.');
            return;
        }

        // ✅ التحقق من الاشتراك في القناة الرسمية
        try {
            $subscription = app(SubscriptionService::class);

            if (! $subscription->isSubscribed($telegramUser->id)) {
                $subscription->sendSubscriptionMessage($bot);
                return;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Subscription check failed', [
                'telegram_id' => $telegramUser->id,
                'error'       => $e->getMessage(),
            ]);
            // استمر — لا تعطّل البوت
        }

        // ============================================================
        //  🎁 أولاً: هل الأمر /start redeem_GIFT_XXXX ؟
        // ============================================================
        if ($this->tryHandleGiftCode($bot, $telegramUser)) {
            return;  // تم التعامل مع الكود
        }

        // ============================================================
        //  📎 استخراج كود الإحالة
        // ============================================================
        $referralCode = $this->extractReferralCode($bot);

        // ============================================================
        //  🔧 الصيانة
        // ============================================================
        if ((bool) Setting::get('maintenance_mode', false)) {
            $user = User::withTrashed()
                ->where('telegram_id', $telegramUser->id)
                ->first();

            if (! $user || ! $user->is_admin) {
                $this->showMaintenance($bot);
                return;
            }
        }

        // ============================================================
        //  🔍 المستخدم
        // ============================================================
        $user = User::withTrashed()
            ->where('telegram_id', $telegramUser->id)
            ->first();

        // 🗑️ حساب محذوف
        if ($user && $user->trashed()) {
            $bot->sendMessage(
                text: implode("\n", [
                    '⚡ <b>VEXORA</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🗑️ <b>حسابك محذوف</b>',
                    '',
                    '📛 <b>اسم المستخدم:</b>',
                    '<code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
                    '',
                    '🗑️ <b>تاريخ الحذف:</b> ' . $user->deleted_at?->format('Y-m-d H:i'),
                    '',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '⚠️ تواصل مع الإدارة لاستعادة حسابك.',
                ]),
                parse_mode: 'HTML',
            );
            return;
        }

        // ✅ مستخدم جديد — تمرير كود الإحالة
        if (! $user) {
            if ($referralCode) {
                Cache::put(
                    'referral_code_' . $bot->userId(),
                    $referralCode,
                    now()->addMinutes(30),
                );
            }

            RegisterConversation::begin(
                bot: $bot,
                userId: $bot->userId(),
                chatId: $bot->chatId(),
            );
            return;
        }

        // 🔴 حساب معطّل
        if (! $user->is_active) {
            $bot->sendMessage(
                text: implode("\n", [
                    '⚡ <b>VEXORA</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '🔴 <b>الحساب غير مفعل</b>',
                    '',
                    'تواصل مع الإدارة.',
                ]),
                parse_mode: 'HTML',
            );
            return;
        }

        // ============================================================
        //  👑 Admin Dashboard
        // ============================================================
        if ($user->is_admin) {
            Log::info('StartCommand: admin dashboard', [
                'admin_id' => $user->id,
                'username' => $user->username,
            ]);

            try {
                $bot->sendMessage(
                    text: DashboardScreen::text($user),
                    reply_markup: DashboardKeyboard::make($user->isSuperAdmin()),
                    parse_mode: 'HTML',
                );
            } catch (\Throwable $e) {
                Log::error('StartCommand: admin dashboard failed', [
                    'error' => $e->getMessage(),
                ]);
            }
            return;
        }

        // ============================================================
        //  👤 User Dashboard
        // ============================================================
        app(UserDashboardHandler::class)->index($bot);
    }

    // ============================================================
    //  🎁 معالج كود الهدية (redeem_GIFT_XXXX)
    // ============================================================
    private function tryHandleGiftCode(Nutgram $bot, $telegramUser): bool
    {
        try {
            $text = $this->extractMessageText($bot);

            // هل يحتوي على redeem_؟
            if (! preg_match('/\/start\s+redeem_(GIFT_[A-Z0-9]{4,16})/i', $text, $matches)) {
                return false;  // ليس كود هدية
            }

            $code = strtoupper($matches[1]);

            Log::info('StartCommand: gift code detected', [
                'telegram_id' => $bot->userId(),
                'code'        => $code,
            ]);

            // ✅ تحقق: المستخدم مسجّل؟
            $user = User::withTrashed()
                ->where('telegram_id', $telegramUser->id)
                ->first();

            if (! $user) {
                $bot->sendMessage(
                    text: implode("\n", [
                        '🎁 <b>كود هدية</b>',
                        '━━━━━━━━━━━━━━━━━━',
                        '',
                        '⚠️ يجب أن تسجّل أولاً قبل استخدام الكود.',
                        '',
                        '💡 أرسل /start للتسجيل ثم أعد المحاولة.',
                        '',
                        '🔐 الكود محفوظ: <code>' . $code . '</code>',
                    ]),
                    parse_mode: 'HTML',
                );

                // احفظ الكود مؤقتاً للاستخدام لاحقاً
                Cache::put(
                    'pending_gift_code_' . $bot->userId(),
                    $code,
                    now()->addMinutes(60),
                );

                return true;
            }

            if ($user->trashed()) {
                $bot->sendMessage(
                    text: '🗑️ حسابك محذوف. تواصل مع الإدارة.',
                );
                return true;
            }

            if (! $user->is_active) {
                $bot->sendMessage(text: '🚫 حسابك موقوف. تواصل مع الدعم.');
                return true;
            }

            // ============================================================
            //  🛡️ حماية: منع الأدمن والسوبر أدمن
            // ============================================================
            if ($user->is_admin || $user->is_super_admin) {
                $bot->sendMessage(
                    text: implode("\n", [
                        '🚫 <b>غير مسموح</b>',
                        '━━━━━━━━━━━━━━━━━━',
                        '',
                        'أكواد الهدايا مخصصة للمستخدمين فقط.',
                        '',
                        '🎯 أنت ' . ($user->is_super_admin ? 'سوبر أدمن' : 'أدمن') . '.',
                        '',
                        '💡 لاختبار الأكواد، استخدم حساب مستخدم آخر.',
                    ]),
                    parse_mode: 'HTML',
                );
                return true;
            }

            // ============================================================
            //  ✅ استبدل الكود
            // ============================================================
            $service = app(GiftCodeService::class);
            $result = $service->redeem($user->id, $code);

            if ($result['ok']) {
                $bot->sendMessage(
                    text: implode("\n", [
                        '🎉 <b>مبروك!</b>',
                        '━━━━━━━━━━━━━━━━━━',
                        '',
                        '✅ تم استبدال الكود بنجاح.',
                        '',
                        '💰 المبلغ المضاف:',
                        '└── <b>' . number_format($result['value'], 2) . ' ' . $result['currency'] . '</b>',
                        '',
                        '🎁 شكراً لاستخدامك البوت!',
                    ]),
                    parse_mode: 'HTML',
                );

                // تحديث رسالة القناة
                $this->updateGiftChannelMessage($bot, $result['gift']);
            } else {
                $bot->sendMessage(
                    text: $result['msg'],
                    parse_mode: 'HTML',
                );
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('tryHandleGiftCode failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    // ============================================================
    //  🎁 تحديث رسالة القناة بعد الاستبدال
    // ============================================================
    private function updateGiftChannelMessage(Nutgram $bot, $gift): void
    {
        if (! $gift->channel_id || ! $gift->channel_msg_id) {
            return;
        }

        try {
            $remaining = max(0, $gift->max_uses - $gift->used_count);

            $text = implode("\n", [
                '🎁 <b>كود هدية</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🔐 الكود: <code>' . $gift->code . '</code>',
                '💰 القيمة: <b>' . $gift->value . ' ' . $gift->currency . '</b>',
                $gift->status === 'used' || $remaining === 0
                    ? '✅ <b>تم استخدام هذا الكود بالكامل</b>'
                    : '👥 متبقٍ: <b>' . $remaining . '</b> مستخدم',
            ]);

            $bot->editMessageText(
                text: $text,
                chat_id: $gift->channel_id,
                message_id: $gift->channel_msg_id,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to update channel message', [
                'gift_id' => $gift->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  📎 استخراج كود الإحالة
    // ============================================================
    private function extractReferralCode(Nutgram $bot): ?string
    {
        try {
            $text = $this->extractMessageText($bot);

            Log::info('StartCommand: extractReferralCode', [
                'text'    => $text ?: 'EMPTY',
                'user_id' => $bot->userId(),
            ]);

            if (preg_match('/\/start\s+ref_([A-Za-z0-9]+)/i', $text, $matches)) {
                $code = $matches[1];

                if (! empty($code)) {
                    Cache::put(
                        'referral_code_' . $bot->userId(),
                        $code,
                        now()->addMinutes(30),
                    );

                    Log::info('StartCommand: referral code extracted', [
                        'telegram_id'   => $bot->userId(),
                        'referral_code' => $code,
                    ]);

                    return $code;
                }
            }
        } catch (\Throwable $e) {
            Log::error('extractReferralCode failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    // ============================================================
    //  📝 استخراج نص الرسالة (بشكل موحّد)
    // ============================================================
    private function extractMessageText(Nutgram $bot): string
    {
        try {
            // من update مباشرة (الأكثر موثوقية)
            $update = $bot->update();

            if ($update && $update->message) {
                $text = $update->message->text ?? '';
                if (! empty($text)) {
                    return $text;
                }
            }

            // من message
            $message = $bot->message();

            return $message?->text ?? '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    // ============================================================
    //  🔧 الصيانة
    // ============================================================
    private function showMaintenance(Nutgram $bot): void
    {
        $message = Setting::get('maintenance_message', 'البوت تحت الصيانة، عد قريبًا');

        $bot->sendMessage(
            text: implode("\n", [
                '🔧 <b>البوت تحت الصيانة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                $message,
                '',
                '🙏 نعتذر عن الإزعاج',
                '⏳ سنعود قريبًا',
            ]),
            parse_mode: 'HTML',
        );
    }

    // ============================================================
}
