<?php

namespace App\Telegram\Handlers\User;

use App\Models\User;
use App\Models\Wheel;
use App\Models\WheelPrize;
use App\Models\WheelSpin;
use App\Services\WheelService;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;
use Illuminate\Support\Facades\Log;

class UserWheelHandler
{
    // ============================================================
    //  🎡 عرض العجلة
    // ============================================================
    public function index(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        $user = User::where('telegram_id', $bot->userId())->first();

        if (! $user) {
            $bot->sendMessage(text: '❌ تعذر التعرف على حسابك.');
            return;
        }

        $state = app(WheelService::class)->getState($user);

        if (! $state) {
            $this->safeEdit($bot, implode("\n", [
                '🎡 <b>عجلة الحظ</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '⚠️ العجلة غير مفعّلة حالياً.',
            ]), InlineKeyboardMarkup::make()->addRow(
                // ─── ↩️ رجوع (بدون لون) ───
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'user.dashboard',
                ),
            ));
            return;
        }

        $wheel = $state['wheel'];
        $webAppUrl = env('WHEEL_WEBAPP_URL', url('/wheel'));

        $text = implode("\n", [
            '🎡 <b>عجلة الحظ</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '🎟 <b>اللفات المتاحة:</b> <b>' . $state['available_spins'] . '</b>',
            '',
            '📅 <b>اليوم:</b> <b>' . $state['spins_today'] . '</b> / <b>' . $wheel->daily_limit . '</b>',
            '⏳ <b>متبقٍ اليوم:</b> <b>' . $state['remaining_today'] . '</b>',
            '',
            '💰 <b>إجمالي المكاسب:</b> <b>' . number_format($state['total_won_amount'], 2) . '</b> NSP',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '💡 اضغط 🎰 لبدء اللفة!',
        ]);

        $keyboard = InlineKeyboardMarkup::make();

        $available = $state['available_spins'];
        $today     = $state['spins_today'];
        $limit     = $wheel->daily_limit;
        $canSpin   = $available > 0 && $today < $limit;

        // ─── 🎰 بدء اللفة ───
        if ($canSpin) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🎰 بدء اللفة (' . $available . ')',
                    web_app: WebAppInfo::make($webAppUrl),
                    style: ButtonStyle::SUCCESS,
                ),
            );
        } elseif ($available <= 0) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🚫 لا توجد لفات',
                    callback_data: 'user.wheel.refresh',
                    style: ButtonStyle::DANGER,
                ),
            );
        } else {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '⏰ وصلت الحد اليومي',
                    callback_data: 'user.wheel.refresh',
                    style: ButtonStyle::DANGER,
                ),
            );
        }

        // ─── 📜 السجل ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '📜 سجل اللفات',
                callback_data: 'user.wheel.history',
                style: ButtonStyle::PRIMARY,
            ),
        );

        // ─── 🔄 تحديث ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '🔄 تحديث',
                callback_data: 'user.wheel.refresh',
                style: ButtonStyle::PRIMARY,
            ),
        );

        // ─── ↩️ رجوع (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '↩️ رجوع للوحة',
                callback_data: 'user.dashboard',
            ),
        );

        $this->safeEdit($bot, $text, $keyboard);
    }

    // ============================================================
    //  🎰 تنفيذ اللفة
    // ============================================================
    public function spin(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery(
                text: '🎡 افتح عجلة الحظ للعب',
                show_alert: true,
            );
        } catch (\Throwable $e) {
        }
    }

    // ============================================================
    //  📜 سجل اللفات
    // ============================================================
    public function history(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        $user = User::where('telegram_id', $bot->userId())->first();

        if (! $user) {
            return;
        }

        $spins = WheelSpin::forUser($user->id)
            ->latestFirst()
            ->limit(10)
            ->get();

        if ($spins->isEmpty()) {
            try {
                $bot->answerCallbackQuery(
                    text: '📭 لا يوجد سجل لفات بعد',
                    show_alert: true,
                );
            } catch (\Throwable $e) {
            }
            return;
        }

        $text = "📜 <b>آخر 10 لفات</b>\n";
        $text .= "━━━━━━━━━━━━━━━━━━\n\n";

        foreach ($spins as $spin) {
            $icon = $spin->prize?->icon ?? '❓';
            $name = $spin->prize?->name ?? 'محذوفة';
            $date = $spin->created_at->format('Y-m-d H:i');
            $value = (float) $spin->won_value > 0
                ? ' — ' . number_format((float) $spin->won_value, 2)
                : '';

            $text .= "{$icon} {$name}{$value}\n";
            $text .= "   <i>{$date}</i>\n\n";
        }

        $keyboard = InlineKeyboardMarkup::make()
            // ─── ↩️ رجوع للعجلة ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع للعجلة',
                    callback_data: 'user.wheel.refresh',
                    style: ButtonStyle::PRIMARY,
                ),
            )
            // ─── 🔙 لوحة التحكم (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🔙 لوحة التحكم',
                    callback_data: 'user.dashboard',
                ),
            );

        $this->safeEdit($bot, $text, $keyboard);
    }

    // ============================================================
    //  Helpers
    // ============================================================
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
                Log::warning('Wheel safeEdit failed', ['error' => $e2->getMessage()]);
            }
        }
    }
}
