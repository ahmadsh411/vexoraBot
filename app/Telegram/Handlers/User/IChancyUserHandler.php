<?php

namespace App\Telegram\Handlers\User;

use App\Models\Setting;
use App\Models\User;
use App\Services\IChancy\IChancyAccountService;
use App\Services\IChancy\IChancyTransferService;
use App\Telegram\Keyboards\User\IChancyDepositKeyboard;
use App\Telegram\Keyboards\User\IChancyWithdrawKeyboard;
use App\Telegram\Screens\User\IChancyDepositScreen;
use App\Telegram\Screens\User\IChancyWithdrawScreen;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class IChancyUserHandler
{
    // ============================================================
    //  💰 شحن IChancy — مع فحص مبكر لرصيد الكاشير
    // ============================================================
    public function deposit(Nutgram $bot): void
    {
        $user = $this->getUser($bot);
        if (! $user) return;

        $this->safeAnswer($bot);

        // ✅ 1. إظهار رسالة الانتظار
        $this->safeEdit(
            $bot,
            "⏳ <b>جاري التحميل...</b>\n\n" .
                "🔄 يتم جلب بيانات حسابك",
            InlineKeyboardMarkup::make(),
        );

        // ✅ 2. التحقق من الحساب
        if (! $user->ichancyAccount) {
            $this->safeEdit(
                $bot,
                "⚠️ <b>لا يوجد حساب IChancy</b>\n\n" .
                    "سيتم إنشاؤه تلقائياً عند التسجيل.\n" .
                    "تواصل مع الدعم للمساعدة.",
                InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '↩️ رجوع',
                            callback_data: 'user.dashboard',
                        ),
                    ),
            );
            return;
        }

        // ✅ 3. التحقق من التفعيل
        if (! (bool) Setting::get('ichancy.enabled', true)) {
            $this->safeAnswer($bot, '⚠️ خدمة IChancy معطلة حالياً', true);
            $this->safeEdit(
                $bot,
                "⚠️ <b>الخدمة معطلة</b>\n\n" .
                    "خدمة IChancy معطلة حالياً.\n" .
                    "الرجاء المحاولة لاحقاً.",
                InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '↩️ رجوع',
                            callback_data: 'user.dashboard',
                        ),
                    ),
            );
            return;
        }

        // ✅ 4. ⚡ فحص مبكر لرصيد الكاشير
        $cashierBalance = app(IChancyTransferService::class)->getCashierBalance();
        $minDeposit     = (int) Setting::get('ichancy.min_deposit', 100);

        if ($cashierBalance !== null && $cashierBalance < $minDeposit) {
            $this->safeEdit(
                $bot,
                "⚠️ <b>الخدمة غير متاحة مؤقتاً</b>\n\n" .
                    "💳 الكاشير لا يملك رصيداً كافياً حالياً.\n" .
                    "⏰ يرجى المحاولة بعد بضع دقائق.\n\n" .
                    "🙏 نعتذر عن الإزعاج.",
                InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '↩️ رجوع',
                            callback_data: 'user.dashboard',
                        ),
                    ),
            );
            return;
        }

        // ✅ 5. عرض الشاشة
        $this->safeEdit(
            $bot,
            IChancyDepositScreen::options($user),
            IChancyDepositKeyboard::options(),
        );
    }

    // ============================================================
    //  💰 شحن كامل الرصيد — مع فحص الكاشير
    // ============================================================
    public function depositFull(Nutgram $bot): void
    {
        $user = $this->getUser($bot);
        if (! $user) return;

        $wallet  = $user->wallet;
        $balance = $wallet ? (float) $wallet->balance_nsp : 0;

        $cfg = $this->getSettings();

        if ($balance < $cfg['min_deposit']) {
            $this->safeAnswer(
                $bot,
                '❌ رصيدك أقل من الحد الأدنى (' . number_format($cfg['min_deposit']) . ')',
                true,
            );
            return;
        }

        // ⚡ فحص سريع لرصيد الكاشير
        $cashierBalance = app(IChancyTransferService::class)->getCashierBalance();

        if ($cashierBalance !== null && $cashierBalance < $balance) {
            $this->safeAnswer(
                $bot,
                '⚠️ الكاشير لا يملك رصيداً كافياً حالياً',
                true,
            );
            return;
        }

        Cache::put(
            "ichancy_deposit_amount_{$user->id}",
            $balance,
            now()->addMinutes(10),
        );

        $this->safeAnswer($bot);
        $this->safeEdit(
            $bot,
            IChancyDepositScreen::confirm($user, $balance),
            IChancyDepositKeyboard::confirm(),
        );
    }

    // ============================================================
    //  ✏️ شحن رصيد محدد
    // ============================================================
    public function depositCustom(Nutgram $bot): void
    {
        $user = $this->getUser($bot);
        if (! $user) return;

        $this->safeAnswer($bot);

        Cache::put(
            "ichancy_awaiting_type_{$bot->userId()}",
            'deposit',
            now()->addMinutes(10),
        );

        \App\Telegram\Conversations\User\IChancyAmountConversation::begin($bot);
    }

    // ============================================================
    //  ✅ تأكيد الشحن — مع رسائل خطأ واضحة
    // ============================================================
    public function depositConfirm(Nutgram $bot): void
    {
        $user = $this->getUser($bot);
        if (! $user) return;

        $amount = (float) Cache::pull("ichancy_deposit_amount_{$user->id}");

        if ($amount <= 0) {
            $this->safeAnswer($bot, '⚠️ انتهت صلاحية العملية', true);
            return;
        }

        $this->safeAnswer($bot);

        // ✅ 1. رسالة تحميل ديناميكية
        $this->safeEdit(
            $bot,
            "⏳ <b>جاري الشحن...</b>\n\n" .
                "📤 يتم إرسال المبلغ إلى IChancy\n" .
                "💰 المبلغ: <code>" . number_format($amount, 2) . " NSP</code>\n\n" .
                "⚡ الرجاء الانتظار...",
            InlineKeyboardMarkup::make(),
        );

        // ✅ 2. التنفيذ
        $result = app(IChancyTransferService::class)->deposit($user, $amount);

        // ✅ 3. معالجة الفشل — رسائل مخصصة حسب نوع الخطأ
        if (! $result['success']) {
            $errorKey = $result['error'] ?? null;

            $errorMessage = match ($errorKey) {
                'cashier_insufficient_balance' =>
                "⚠️ <b>الخدمة غير متاحة مؤقتاً</b>\n\n" .
                    "💳 الكاشير لا يملك رصيداً كافياً حالياً.\n" .
                    "⏰ يرجى المحاولة بعد بضع دقائق.\n\n" .
                    "🙏 نعتذر عن الإزعاج.",

                'cashier_check_failed' =>
                "⚠️ <b>تعذّر الاتصال بالخدمة</b>\n\n" .
                    "🔄 يرجى المحاولة مرة أخرى بعد قليل.\n\n" .
                    "🙏 نعتذر عن الإزعاج.",

                default =>
                "❌ <b>فشل الشحن</b>\n\n" .
                    "📝 <b>السبب:</b>\n" .
                    "<i>" . htmlspecialchars(
                        \App\Helpers\ErrorMessages::friendly($errorKey, 'ichancy'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) . "</i>",
            };

            $this->safeEdit(
                $bot,
                $errorMessage,
                InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '↩️ رجوع',
                            callback_data: 'user.dashboard',
                        ),
                    ),
            );
            return;
        }

        // ✅ 4. نجاح
        $rate      = (int) Setting::get('ichancy.display_rate', 100);
        $amountNps = $amount * $rate;

        $this->safeEdit(
            $bot,
            implode("\n", [
                '✅ <b>تم الشحن بنجاح!</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '💰 <b>من محفظة البوت (NSP):</b>',
                '└── <code>-' . number_format($amount, 2) . ' NSP</code>',
                '',
                '🎮 <b>وصل لحساب IChancy (NPS):</b>',
                '└── <code>+' . number_format($amountNps, 2) . ' NPS</code>',
                '',
                '📊 <b>المرجع:</b>',
                '└── <code>' . $result['transaction']->reference . '</code>',
                '',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🎯 يمكنك اللعب الآن!',
            ]),
            InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '🏠 القائمة الرئيسية',
                        callback_data: 'user.dashboard',
                        style: ButtonStyle::SUCCESS,
                    ),
                ),
        );
    }

    // ============================================================
    //  💸 سحب IChancy — مع رسالة تحميل ديناميكية
    // ============================================================
    public function withdraw(Nutgram $bot): void
    {
        $user = $this->getUser($bot);
        if (! $user) return;

        $this->safeAnswer($bot);

        if (! $user->ichancyAccount) {
            $this->safeAnswer($bot, '⚠️ لا يوجد حساب IChancy', true);
            return;
        }

        if (! (bool) Setting::get('ichancy.enabled', true)) {
            $this->safeAnswer($bot, '⚠️ خدمة IChancy معطلة حالياً', true);
            return;
        }

        // ✅ 1. رسالة تحميل ديناميكية
        $this->safeEdit(
            $bot,
            "⏳ <b>جاري التحميل...</b>\n\n" .
                "🎮 يتم جلب رصيد IChancy\n" .
                "⚡ الرجاء الانتظار...",
            InlineKeyboardMarkup::make(),
        );

        // ✅ 2. جلب الرصيد (بدون cache)
        $balanceData = app(IChancyAccountService::class)->getBalanceForUser($user, 0);
        $balance     = $balanceData ? (float) $balanceData['balance'] : 0;

        // ✅ 3. عرض الشاشة
        $this->safeEdit(
            $bot,
            IChancyWithdrawScreen::options($user, $balance),
            IChancyWithdrawKeyboard::options(),
        );
    }

    // ============================================================
    //  💸 سحب كامل الرصيد
    // ============================================================
    public function withdrawFull(Nutgram $bot): void
    {
        $user = $this->getUser($bot);
        if (! $user) return;

        // ✅ رسالة تحميل
        $this->safeAnswer($bot);
        $this->safeEdit(
            $bot,
            "⏳ <b>جاري التحقق...</b>\n\n" .
                "💰 يتم جلب رصيدك من IChancy",
            InlineKeyboardMarkup::make(),
        );

        $balanceData = app(IChancyAccountService::class)->getBalanceForUser($user, 0);
        $balance     = $balanceData ? (float) $balanceData['balance'] : 0;

        $cfg = $this->getSettings();

        if ($balance < $cfg['min_withdraw']) {
            $this->safeEdit(
                $bot,
                "❌ <b>رصيد غير كافٍ</b>\n\n" .
                    "🎮 رصيد IChancy: <code>" . number_format($balance, 2) . " NSP</code>\n" .
                    "📊 الحد الأدنى: <code>" . number_format($cfg['min_withdraw']) . " NSP</code>",
                InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '↩️ رجوع',
                            callback_data: 'user.ichancy.withdraw',
                        ),
                    ),
            );
            return;
        }

        Cache::put(
            "ichancy_withdraw_amount_{$user->id}",
            $balance,
            now()->addMinutes(10),
        );

        $this->safeEdit(
            $bot,
            IChancyWithdrawScreen::confirm($user, $balance),
            IChancyWithdrawKeyboard::confirm(),
        );
    }

    // ============================================================
    //  ✏️ سحب رصيد محدد
    // ============================================================
    public function withdrawCustom(Nutgram $bot): void
    {
        $user = $this->getUser($bot);
        if (! $user) return;

        $this->safeAnswer($bot);

        Cache::put(
            "ichancy_awaiting_type_{$bot->userId()}",
            'withdraw',
            now()->addMinutes(10),
        );

        \App\Telegram\Conversations\User\IChancyAmountConversation::begin($bot);
    }

    // ============================================================
    //  ✅ تأكيد السحب — مع رسائل خطأ واضحة
    // ============================================================
    public function withdrawConfirm(Nutgram $bot): void
    {
        $user = $this->getUser($bot);
        if (! $user) return;

        $amount = (float) Cache::pull("ichancy_withdraw_amount_{$user->id}");

        if ($amount <= 0) {
            $this->safeAnswer($bot, '⚠️ انتهت صلاحية العملية', true);
            return;
        }

        $this->safeAnswer($bot);

        // ✅ 1. رسالة تحميل
        $this->safeEdit(
            $bot,
            "⏳ <b>جاري السحب...</b>\n\n" .
                "📥 يتم سحب المبلغ من IChancy\n" .
                "💰 المبلغ: <code>" . number_format($amount, 2) . " NSP</code>\n\n" .
                "⚡ الرجاء الانتظار...",
            InlineKeyboardMarkup::make(),
        );

        // ✅ 2. التنفيذ
        $result = app(IChancyTransferService::class)->withdraw($user, $amount);

        // ✅ 3. معالجة الفشل — رسائل مخصصة
        if (! $result['success']) {
            $errorKey = $result['error'] ?? null;

            $errorMessage = match ($errorKey) {
                'cashier_insufficient_balance' =>
                "⚠️ <b>الخدمة غير متاحة مؤقتاً</b>\n\n" .
                    "💳 الكاشير لا يملك رصيداً كافياً حالياً.\n" .
                    "⏰ يرجى المحاولة بعد بضع دقائق.\n\n" .
                    "🙏 نعتذر عن الإزعاج.",

                'cashier_check_failed' =>
                "⚠️ <b>تعذّر الاتصال بالخدمة</b>\n\n" .
                    "🔄 يرجى المحاولة مرة أخرى بعد قليل.\n\n" .
                    "🙏 نعتذر عن الإزعاج.",

                default =>
                "❌ <b>فشل السحب</b>\n\n" .
                    "📝 <b>السبب:</b>\n" .
                    "<i>" . htmlspecialchars(
                        \App\Helpers\ErrorMessages::friendly($errorKey, 'ichancy'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) . "</i>",
            };

            $this->safeEdit(
                $bot,
                $errorMessage,
                InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make(
                            text: '↩️ رجوع',
                            callback_data: 'user.dashboard',
                        ),
                    ),
            );
            return;
        }

        // ✅ 4. نجاح
        $rate      = (int) Setting::get('ichancy.display_rate', 100);
        $amountNps = $amount * $rate;

        $this->safeEdit(
            $bot,
            implode("\n", [
                '✅ <b>تم السحب بنجاح!</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '🎮 <b>من حساب IChancy (NPS):</b>',
                '└── <code>-' . number_format($amountNps, 2) . ' NPS</code>',
                '',
                '💰 <b>وصل لمحفظة البوت (NSP):</b>',
                '└── <code>+' . number_format($amount, 2) . ' NSP</code>',
                '',
                '📊 <b>المرجع:</b>',
                '└── <code>' . $result['transaction']->reference . '</code>',
                '',
                '━━━━━━━━━━━━━━━━━━',
            ]),
            InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '🏠 القائمة الرئيسية',
                        callback_data: 'user.dashboard',
                        style: ButtonStyle::SUCCESS,
                    ),
                ),
        );
    }

    // ============================================================
    //  Helpers
    // ============================================================
    private function getUser(Nutgram $bot): ?User
    {
        return User::where('telegram_id', $bot->userId())->first();
    }

    private function getSettings(): array
    {
        return [
            'currency'     => 'NSP',
            'min_deposit'  => (int) Setting::get('ichancy.min_deposit', 100),
            'max_deposit'  => (int) Setting::get('ichancy.max_deposit', 1000000),
            'min_withdraw' => (int) Setting::get('ichancy.min_withdraw', 100),
            'max_withdraw' => (int) Setting::get('ichancy.max_withdraw', 1000000),
        ];
    }

    private function safeAnswer(Nutgram $bot, ?string $text = null, bool $alert = false): void
    {
        try {
            $bot->answerCallbackQuery(text: $text, show_alert: $alert);
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
                Log::warning('IChancyUserHandler safeEdit failed', [
                    'error' => $e2->getMessage(),
                ]);
            }
        }
    }
}
