<?php

namespace App\Telegram\Conversations;

use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\ReferralService;
use App\Services\UserService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

class RegisterConversation extends Conversation
{
    protected ?string $referralCode = null;

    public function start(Nutgram $bot): void
    {
        $telegramUser = $bot->user();

        if ($telegramUser === null) {
            $bot->sendMessage(text: '❌ تعذر التعرف على حساب Telegram.');
            $this->end();
            return;
        }

        // ✅ التحقق من الاشتراك في القناة الرسمية
        try {
            $subscription = app(SubscriptionService::class);

            if (! $subscription->isSubscribed($telegramUser->id)) {
                $subscription->sendSubscriptionMessage($bot);
                $this->end();
                return;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Register: subscription failed', [
                'telegram_id' => $telegramUser->id,
                'error'       => $e->getMessage(),
            ]);
        }

        $this->referralCode = Cache::get('referral_code_' . $bot->userId());

        Log::info('RegisterConversation: referralCode from cache', [
            'telegram_id'   => $bot->userId(),
            'referral_code' => $this->referralCode ?? 'NULL',
        ]);

        $existing = User::withTrashed()
            ->where('telegram_id', $telegramUser->id)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $bot->sendMessage(
                text: implode("\n", [
                    '⚡ <b>VEXORA</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '♻️ <b>تم استعادة حسابك</b>',
                    '',
                    '📛 <b>اسم المستخدم:</b>',
                    '<code>' . htmlspecialchars($existing->username, ENT_QUOTES, 'UTF-8') . '</code>',
                    '',
                    'استخدم /start للدخول.',
                ]),
                parse_mode: 'HTML',
            );

            $this->end();
            return;
        }

        $this->putState($bot, ['username' => null]);

        // ✅ اقرأ البادئة من الإعدادات
        $prefix = Setting::get('bot_name_prefix', 'Vexora');

        $bot->sendMessage(
            text: implode("\n", [
                '⚡ <b>VEXORA</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👤 <b>إنشاء حساب</b>',
                '',
                '📝 أرسل <b>اسم المستخدم</b>:',
                '',
                '💡 سيصبح: <code>' . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8') . '_اسمك</code>',
                '💡 من 3 إلى 30 حرفاً (إنجليزي وأرقام و _)',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('askUsername');
    }

    // ============================================================
    //  اسم المستخدم
    // ============================================================
    public function askUsername(Nutgram $bot): void
    {
        $username = trim((string) ($bot->message()->text ?? ''));

        if ($username === '') {
            $bot->sendMessage(text: '❌ اسم المستخدم لا يمكن أن يكون فارغاً.');
            return;
        }

        // ✅ البادئة الحالية من الإعدادات
        $prefix = Setting::get('bot_name_prefix', 'Vexora');

        // ✅ إزالة أي بادئة قديمة (حتى لو تغيّرت)
        $oldPrefixes = [
            'Vexora_', 'VEXORA_', 'VexoraBot_',
            'DZ_', 'dz_',
        ];

        foreach ($oldPrefixes as $oldPrefix) {
            if (str_starts_with($username, $oldPrefix)) {
                $username = substr($username, strlen($oldPrefix));
                break;
            }
        }

        // ✅ إزالة البادئة الجديدة إن كتبها المستخدم
        if (str_starts_with($username, $prefix . '_')) {
            $username = substr($username, strlen($prefix) + 1);
        }

        if (! preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            $bot->sendMessage(
                text: implode("\n", [
                    '❌ <b>اسم المستخدم غير صالح.</b>',
                    '',
                    'يُسمح فقط بالحروف الإنجليزية والأرقام و _',
                    'الطول: 3 - 30 حرفاً.',
                    '',
                    '📝 حاول مرة أخرى:',
                ]),
                parse_mode: 'HTML',
            );
            return;
        }

        // ✅ توليد الاسم بالبادئة الحالية
        $username = $prefix . '_' . $username;

        $userService = app(UserService::class);

        if ($userService->existsByUsername($username)) {
            $bot->sendMessage(text: '❌ اسم المستخدم مستخدم مسبقاً. جرب آخر:');
            return;
        }

        $state = $this->getState($bot);
        $state['username'] = $username;
        $this->putState($bot, $state);

        $bot->sendMessage(
            text: implode("\n", [
                '🔐 <b>كلمة المرور</b>',
                '',
                'أرسل كلمة المرور التي تريد استخدامها:',
                '',
                'الحد الأدنى: 6 أحرف.',
            ]),
            parse_mode: 'HTML',
        );

        $this->next('createAccount');
    }

    // ============================================================
    //  كلمة المرور + إنشاء الحساب
    // ============================================================
    public function createAccount(Nutgram $bot): void
    {
        $state = $this->getState($bot);
        $username = $state['username'] ?? null;

        if (! is_string($username) || $username === '') {
            $bot->sendMessage(text: '❌ حدث خطأ. استخدم /start من جديد.');
            $this->clearState($bot);
            $this->end();
            return;
        }

        $password = trim((string) ($bot->message()->text ?? ''));

        if ($password === '' || str_starts_with($password, '/')) {
            $bot->sendMessage(text: '❌ كلمة المرور غير صالحة. حاول مرة أخرى:');
            return;
        }

        if (mb_strlen($password) < 6) {
            $bot->sendMessage(text: '❌ كلمة المرور يجب أن تكون 6 أحرف على الأقل.');
            return;
        }

        $telegramUser = $bot->user();

        if ($telegramUser === null) {
            $bot->sendMessage(text: '❌ تعذر التعرف على حساب Telegram.');
            $this->clearState($bot);
            $this->end();
            return;
        }

        $existing = User::withTrashed()
            ->where('telegram_id', $telegramUser->id)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $bot->sendMessage(
                text: '♻️ تم استعادة حسابك. استخدم /start.',
                parse_mode: 'HTML',
            );

            $this->clearState($bot);
            $this->end();
            return;
        }

        // ============================================================
        //  ✅ 1. رسالة الانتظار (يتم تحديثها بشكل متحرك)
        // ============================================================
        $waitMessage = $bot->sendMessage(
            text: '⏳ <b>جاري الانتظار</b>',
            parse_mode: 'HTML',
        );

        $waitMessageId = $waitMessage?->message_id;

        try {
            $user = app(UserService::class)->create([
                'telegram_id'       => $telegramUser->id,
                'telegram_username' => $telegramUser->username ?? null,
                'username'          => $username,
                'password'          => $password,
            ]);

            // ✅ 2. كود الإحالة
            try {
                $user->update([
                    'referral_code' => app(ReferralService::class)->generateReferralCode(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to generate referral code', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }

            // ✅ 3. ربط الإحالة
            try {
                $referralCode = $this->referralCode
                    ?? Cache::pull('referral_code_' . $bot->userId());

                if ($referralCode) {
                    app(ReferralService::class)->attachReferrer($user, $referralCode);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to attach referrer', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }

            $user->refresh();

            // ✅ 4. تحديث الرسالة إلى "جاري إنشاء الحساب في النظام"
            try {
                if ($waitMessageId) {
                    $bot->editMessageText(
                        text: '⏳ <b>جاري إنشاء حسابك في نظام اللعب...</b>',
                        chat_id: $bot->chatId(),
                        message_id: $waitMessageId,
                        parse_mode: 'HTML',
                    );
                }
            } catch (\Throwable $e) {
                // تجاهل
            }

            // ✅ 5. إنشاء حساب في IChancy
            try {
                $ichancyAccount = app(\App\Services\IChancy\IChancyAccountService::class)
                    ->createForUser($user, $password);

                if ($ichancyAccount) {
                    Log::info('RegisterConversation: IChancy account created', [
                        'user_id'   => $user->id,
                        'player_id' => $ichancyAccount->ichancy_player_id,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('RegisterConversation: IChancy exception', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }

            // ✅ 6. حذف رسالة الانتظار
            try {
                if ($waitMessageId) {
                    $bot->deleteMessage(
                        chat_id: $bot->chatId(),
                        message_id: $waitMessageId,
                    );
                }
            } catch (\Throwable $e) {
                // تجاهل — يمكن الرسالة قديمة
            }

            // ✅ 7. إشعارات الترحيب (الرسالة الكاملة فقط)
            $notificationService = app(NotificationService::class);

            try {
                $notificationService->sendWelcomeToUser($bot, $user, $password);
            } catch (\Throwable $e) {
                Log::warning('Welcome user failed', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }

            try {
                $notificationService->sendWelcomeToChannel($bot, $user, $password);
                // 🎁 مكافأة التسجيل
                try {
                    $bonusService = app(\App\Services\SignupBonusService::class);
                    $bonusResult = $bonusService->apply($user);

                    if (($bonusResult['applied'] ?? false) === true) {
                        // 🔔 إشعار المستخدم بالمكافأة
                        $this->notifySignupBonus($bot, $user, $bonusResult);

                        // 📢 إشعار القناة
                        $this->notifyChannelSignupBonus($bot, $user, $bonusResult);

                        Log::info('Signup bonus applied', [
                            'user_id' => $user->id,
                            'nsp'     => $bonusResult['nsp'],
                            'usd'     => $bonusResult['usd'],
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Signup bonus failed', [
                        'user_id' => $user->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Welcome channel failed', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Registration failed', [
                'telegram_id' => $telegramUser->id,
                'error'       => $e->getMessage(),
            ]);

            // ✅ حذف رسالة الانتظار عند الفشل
            try {
                if ($waitMessageId) {
                    $bot->deleteMessage(
                        chat_id: $bot->chatId(),
                        message_id: $waitMessageId,
                    );
                }
            } catch (\Throwable $e) {
                // تجاهل
            }

            $bot->sendMessage(
                text: '❌ فشل إنشاء الحساب: ' . $e->getMessage(),
            );
        }

        $this->clearState($bot);
        $this->end();
    }

    /**
     * 🔔 إشعار المستخدم بمكافأة التسجيل
     */
    private function notifySignupBonus(Nutgram $bot, User $user, array $bonus): void
    {
        if (! $user->telegram_id) return;

        $lines = [
            '🎉 <b>مبروك!</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '✅ <b>تم إنشاء حسابك بنجاح</b>',
            '',
            '🎁 <b>هدية التسجيل:</b>',
        ];

        if (($bonus['nsp'] ?? 0) > 0) {
            $lines[] = '├── 💰 NSP: <b>+' . number_format($bonus['nsp'], 2) . '</b>';
        }

        if (($bonus['usd'] ?? 0) > 0) {
            $lines[] = '└── 💵 USD: <b>+' . number_format($bonus['usd'], 2) . '</b>';
        }

        $lines[] = '';
        $lines[] = '💎 <i>تمت إضافة الهدية إلى محفظتك</i>';
        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━';
        $lines[] = '';
        $lines[] = '🎯 ابدأ الآن من القائمة الرئيسية!';

        try {
            $bot->sendMessage(
                text: implode("\n", $lines),
                chat_id: $user->telegram_id,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            Log::warning('Signup bonus notification failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * 📢 إشعار القناة بمكافأة التسجيل
     */
    private function notifyChannelSignupBonus(Nutgram $bot, User $user, array $bonus): void
    {
        try {
            $lines = [
                '🎁 <b>مكافأة تسجيل جديدة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '👤 <b>المستخدم:</b> <code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>',
                '🆔 <b>المعرف:</b> <code>' . $user->id . '</code>',
                '',
                '💰 <b>المكافأة:</b>',
            ];

            if (($bonus['nsp'] ?? 0) > 0) {
                $lines[] = '├── 💰 NSP: <b>' . number_format($bonus['nsp'], 2) . '</b>';
            }

            if (($bonus['usd'] ?? 0) > 0) {
                $lines[] = '└── 💵 USD: <b>' . number_format($bonus['usd'], 2) . '</b>';
            }

            $lines[] = '';
            $lines[] = '📅 ' . now()->format('Y-m-d H:i');

            app(\App\Services\NotificationService::class)->notifyGeneralChannel(
                $bot,
                implode("\n", $lines),
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about signup bonus', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  State
    // ============================================================
    private function stateKey(Nutgram $bot): string
    {
        return 'register_state_' . $bot->userId() . '_' . $bot->chatId();
    }

    private function getState(Nutgram $bot): array
    {
        return Cache::get($this->stateKey($bot), []);
    }

    private function putState(Nutgram $bot, array $state): void
    {
        Cache::put($this->stateKey($bot), $state, now()->addMinutes(30));
    }

    private function clearState(Nutgram $bot): void
    {
        Cache::forget($this->stateKey($bot));
    }
}
