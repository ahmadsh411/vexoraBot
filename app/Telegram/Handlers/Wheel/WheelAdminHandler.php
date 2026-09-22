<?php

namespace App\Telegram\Handlers\Wheel;

use App\Models\User;
use App\Models\Wheel;
use App\Models\WheelPrize;
use App\Models\WheelSpin;
use App\Models\WheelUserState;
use App\Services\WheelAdminService;
use App\Services\WheelService;
use App\Telegram\Keyboards\AdminsKeyboard\Wheel\WheelAdminKeyboard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class WheelAdminHandler
{
    public function __construct(
        private readonly WheelAdminService $wheelAdminService,
        private readonly WheelService $wheelService,
    ) {}

    private const SETTING_LABELS = [
        'deposit_syp_threshold'     => '💰 قيمة اللفة (NSP)',
        'min_deposit_syp_threshold' => '💰 الحد الأدنى لقيمة اللفة (NSP)',
        'max_deposit_syp_threshold' => '💰 الحد الأقصى لقيمة اللفة (NSP)',

        'deposit_usd_threshold'     => '💵 قيمة اللفة (USD)',
        'min_deposit_usd_threshold' => '💵 الحد الأدنى لقيمة اللفة (USD)',
        'max_deposit_usd_threshold' => '💵 الحد الأقصى لقيمة اللفة (USD)',

        'referral_threshold'        => '🔗 عدد لفات الإحالة',
        'min_referral_threshold'    => '🔗 الحد الأدنى للفات الإحالة',
        'max_referral_threshold'    => '🔗 الحد الأقصى للفات الإحالة',

        'daily_limit'               => '📊 الحد اليومي للفات',
        'min_daily_limit'           => '📊 الحد الأدنى للحد اليومي',
        'max_daily_limit'           => '📊 الحد الأقصى للحد اليومي',

        'max_stored_spins'          => '📦 أقصى عدد لفات مخزّنة',
    ];

    // ============================================================
    //  🛠️ دالة عرض موحّدة
    // ============================================================

    protected function render(Nutgram $bot, string $text, $keyboard): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

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
            } catch (\Throwable $inner) {
                Log::error('Wheel render error', [
                    'error' => $inner->getMessage(),
                ]);
            }
        }
    }

    // ============================================================
    //  🏠 index
    // ============================================================

    public function index(Nutgram $bot): void
    {
        $wheel = Wheel::firstOrFail();
        $status = $wheel->is_active ? '✅ مفعّلة' : '❌ معطّلة';

        $text = "🎡 <b>إدارة عجلة الحظ</b>\n\n"
            . "📌 الحالة: {$status}\n"
            . "💰 قيمة اللفة (NSP): <b>" . number_format((float) $wheel->deposit_syp_threshold, 0) . "</b>\n"
            . "💵 قيمة اللفة (USD): <b>" . number_format((float) $wheel->deposit_usd_threshold, 2) . "</b>\n"
            . "📊 الحد اليومي: <b>{$wheel->daily_limit}</b>\n"
            . "🎁 عدد الجوائز: <b>" . $wheel->prizes()->count() . "</b>\n\n";

        $this->render($bot, $text, WheelAdminKeyboard::main());
    }

    // ============================================================
    //  ⚙️ settings
    // ============================================================

    public function settings(Nutgram $bot): void
    {
        $wheel = Wheel::firstOrFail();

        $text = "⚙️ <b>إعدادات العجلة</b>\n\n"
            . "💰 NSP: <b>" . number_format((float) $wheel->deposit_syp_threshold, 0) . "</b>\n"
            . "💰 حد أدنى NSP: <b>" . number_format((float) $wheel->min_deposit_syp_threshold, 0) . "</b>\n"
            . "💰 حد أقصى NSP: <b>" . number_format((float) $wheel->max_deposit_syp_threshold, 0) . "</b>\n\n"
            . "💵 USD: <b>" . number_format((float) $wheel->deposit_usd_threshold, 2) . "</b>\n"
            . "💵 حد أدنى USD: <b>" . number_format((float) $wheel->min_deposit_usd_threshold, 2) . "</b>\n"
            . "💵 حد أقصى USD: <b>" . number_format((float) $wheel->max_deposit_usd_threshold, 2) . "</b>\n\n"
            . "🔗 لفات الإحالة: <b>{$wheel->referral_threshold}</b>\n"
            . "📊 الحد اليومي: <b>{$wheel->daily_limit}</b>\n"
            . "📦 أقصى لفات مخزنة: <b>{$wheel->max_stored_spins}</b>\n";

        $this->render($bot, $text, WheelAdminKeyboard::settings());
    }

    public function editSetting(Nutgram $bot, string $key): void
    {
        if (! array_key_exists($key, self::SETTING_LABELS)) {
            try {
                $bot->answerCallbackQuery(text: 'إعداد غير معروف', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        Cache::put("wheel_admin_edit_{$bot->userId()}", $key, now()->addMinutes(10));

        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        $label = self::SETTING_LABELS[$key];

        // ✅ احفظ رسالة السؤال
        $msg = $bot->sendMessage(
            text: "✏️ أرسل القيمة الجديدة لـ <b>{$label}</b>:",
            parse_mode: 'HTML',
        );

        if ($msg?->message_id) {
            Cache::put(
                "wheel_admin_edit_msg_{$bot->userId()}",
                $msg->message_id,
                now()->addMinutes(10),
            );
        }
    }

    public function toggleActive(Nutgram $bot): void
    {
        $wheel = Wheel::firstOrFail();
        $admin = User::where('telegram_id', $bot->userId())->first();

        $newState = $this->wheelAdminService->toggleActive($wheel, $admin);

        try {
            $bot->answerCallbackQuery(
                text: $newState ? '✅ تم التفعيل' : '❌ تم التعطيل',
                show_alert: true,
            );
        } catch (\Throwable $e) {
        }

        $this->index($bot);
    }

    // ============================================================
    //  🎁 prizes
    // ============================================================

    public function prizes(Nutgram $bot): void
    {
        $wheel = Wheel::firstOrFail();
        $prizes = $wheel->prizes()->orderBy('sort_order')->get();
        $totalPercent = (int) $prizes->sum('weight');

        $text = "🎁 <b>جوائز العجلة</b>\n\n";

        $status = $totalPercent === 100 ? '✅' : '⚠️';
        $text .= "📊 مجموع النسب: <b>{$totalPercent}%</b> {$status}\n\n";

        foreach ($prizes as $p) {
            $active = $p->is_active ? '🟢' : '🔴';
            $typeLabel = match ($p->type) {
                'balance' => '💰',
                'empty'   => '😢',
                'recycle' => '♻️',
                default   => '❓',
            };

            $text .= "{$active} {$typeLabel} {$p->icon} <b>{$p->name}</b> — {$p->weight}%\n";

            if ($p->type === 'balance') {
                $text .= "   💰 {$p->value} {$p->currency}\n";
            }

            $text .= "\n";
        }

        $this->render($bot, $text, WheelAdminKeyboard::prizes());
    }

    public function showPrize(Nutgram $bot, ?string $id = null): void
    {
        $id = (int) ($id ?? 0);

        if ($id <= 0) {
            try {
                $bot->answerCallbackQuery(text: '❌ معرّف غير صالح', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        $prize = WheelPrize::find($id);

        if (! $prize) {
            try {
                $bot->answerCallbackQuery(text: '❌ الجائزة غير موجودة', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        $text = "🎁 <b>{$prize->name}</b>\n\n"
            . "📝 الأيقونة: {$prize->icon}\n"
            . "🎨 اللون: {$prize->color}\n"
            . "💰 القيمة: <b>{$prize->value} {$prize->currency}</b>\n"
            . "📊 النوع: {$prize->type_label}\n"
            . "🎯 النسبة: <b>{$prize->weight}%</b>\n"
            . "الحالة: " . ($prize->is_active ? '✅' : '❌') . "\n";

        $this->render($bot, $text, WheelAdminKeyboard::prizeDetails($prize));
    }

    public function togglePrize(Nutgram $bot, ?string $id = null): void
    {
        $id = (int) ($id ?? 0);

        if ($id <= 0) {
            try {
                $bot->answerCallbackQuery(text: '❌ معرّف غير صالح', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        $prize = WheelPrize::find($id);

        if (! $prize) {
            try {
                $bot->answerCallbackQuery(text: '❌ الجائزة غير موجودة', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        $admin = User::where('telegram_id', $bot->userId())->first();
        $newState = $this->wheelAdminService->togglePrize($prize, $admin);

        try {
            $bot->answerCallbackQuery(
                text: $newState ? '✅ تم التفعيل' : '❌ تم التعطيل',
                show_alert: true,
            );
        } catch (\Throwable $e) {
        }

        $this->showPrize($bot, (string) $id);
    }

    public function deletePrize(Nutgram $bot, ?string $id = null): void
    {
        $id = (int) ($id ?? 0);

        if ($id <= 0) {
            try {
                $bot->answerCallbackQuery(text: '❌ معرّف غير صالح', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        $prize = WheelPrize::find($id);

        if (! $prize) {
            try {
                $bot->answerCallbackQuery(text: '❌ الجائزة غير موجودة', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        $this->render(
            $bot,
            "⚠️ هل أنت متأكد من حذف الجائزة <b>{$prize->name}</b>؟",
            WheelAdminKeyboard::confirmDeletePrize($prize),
        );
    }

    public function deletePrizeConfirm(Nutgram $bot, ?string $id = null): void
    {
        $id = (int) ($id ?? 0);

        if ($id <= 0) {
            try {
                $bot->answerCallbackQuery(text: '❌ معرّف غير صالح', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        $prize = WheelPrize::find($id);

        if (! $prize) {
            try {
                $bot->answerCallbackQuery(text: '❌ الجائزة غير موجودة', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        $name = $prize->name;
        $admin = User::where('telegram_id', $bot->userId())->first();

        $this->wheelAdminService->deletePrize($prize, $admin);

        try {
            $bot->answerCallbackQuery(text: "🗑 تم حذف: {$name}", show_alert: true);
        } catch (\Throwable $e) {
        }

        $this->prizes($bot);
    }

    public function createPrize(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        \App\Telegram\Conversations\Admin\Wheel\CreateWheelPrizeConversation::begin($bot);
    }

    public function editPrize(Nutgram $bot, ?string $id = null): void
    {
        $id = (int) ($id ?? 0);

        if ($id <= 0) {
            try {
                $bot->answerCallbackQuery(text: '❌ معرّف غير صالح', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        $prize = WheelPrize::find($id);

        if (! $prize) {
            try {
                $bot->answerCallbackQuery(text: '❌ الجائزة غير موجودة', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        Cache::put("wheel_prize.edit.id.{$bot->userId()}", $id, now()->addMinutes(10));

        \App\Telegram\Conversations\Admin\Wheel\EditWheelPrizeConversation::begin($bot);
    }

    public function weightPrize(Nutgram $bot, ?string $id = null): void
    {
        $id = (int) ($id ?? 0);

        if ($id <= 0) {
            try {
                $bot->answerCallbackQuery(text: '❌ معرّف غير صالح', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        $prize = WheelPrize::find($id);

        if (! $prize) {
            try {
                $bot->answerCallbackQuery(text: '❌ الجائزة غير موجودة', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        $userId = $bot->userId();

        Cache::forget("wheel_admin_edit_{$userId}");
        Cache::forget("wheel_admin_new_prize_{$userId}");
        Cache::forget("wheel_admin_edit_prize_{$userId}");
        Cache::forget("wheel_admin_user_search_{$userId}");

        Cache::put("wheel_admin_edit_percent_{$userId}", $id, now()->addMinutes(10));

        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        $bot->sendMessage(
            text: "🎯 <b>{$prize->name}</b>\n\n"
                . "النسبة الحالية: <b>{$prize->weight}%</b>\n\n"
                . "أرسل <b>النسبة الجديدة</b> (0-100):",
            parse_mode: 'HTML',
        );
    }

    // ============================================================
    //  📊 stats
    // ============================================================

    public function stats(Nutgram $bot): void
    {
        $stats = $this->wheelService->getStats();

        $text = "📊 <b>إحصائيات العجلة</b>\n\n"
            . "🎯 إجمالي اللفات: <b>{$stats['total_spins']}</b>\n"
            . "📅 لفات اليوم: <b>{$stats['today_spins']}</b>\n"
            . "💰 إجمالي الجوائز: <b>" . number_format($stats['total_won'], 2) . "</b>\n"
            . "💰 جوائز اليوم: <b>" . number_format($stats['today_won'], 2) . "</b>\n";

        $this->render($bot, $text, WheelAdminKeyboard::stats());
    }

    // ============================================================
    //  📜 history
    // ============================================================

    public function history(Nutgram $bot, ?string $page = null): void
    {
        $page = (int) ($page ?? 1);

        if ($page <= 0) {
            $page = 1;
        }

        $spins = WheelSpin::with(['user', 'prize'])
            ->latestFirst()
            ->paginate(10, ['*'], 'page', $page);

        $text = "📜 <b>سجل اللفات (صفحة {$page})</b>\n\n";

        foreach ($spins as $s) {
            $name = $s->user?->username ?? 'مستخدم';
            $prize = $s->prize?->name ?? '—';
            $text .= "👤 {$name} → 🎁 {$prize}\n";
        }

        $this->render($bot, $text, WheelAdminKeyboard::history($page));
    }

    // ============================================================
    //  👤 userSpins
    // ============================================================

    public function userSpins(Nutgram $bot): void
    {
        Cache::put("wheel_admin_user_search_{$bot->userId()}", true, now()->addMinutes(10));

        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        $bot->sendMessage(text: "👤 أرسل معرف أو اسم المستخدم للبحث:");
    }

    public function grantSpin(Nutgram $bot, ?string $id = null): void
    {
        $id = (int) ($id ?? 0);

        if ($id <= 0) {
            try {
                $bot->answerCallbackQuery(text: '❌ معرّف غير صالح', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        $user = User::find($id);

        if (! $user) {
            try {
                $bot->answerCallbackQuery(text: '❌ مستخدم غير موجود', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        $granted = $this->wheelService->grantSpins($user, 1);

        try {
            $bot->answerCallbackQuery(
                text: "✅ تم منح {$granted} لفة لـ {$user->username}",
                show_alert: true,
            );
        } catch (\Throwable $e) {
        }
    }

    public function resetDaily(Nutgram $bot, ?string $id = null): void
    {
        $id = (int) ($id ?? 0);

        if ($id <= 0) {
            try {
                $bot->answerCallbackQuery(text: '❌ معرّف غير صالح', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        $user = User::find($id);

        if (! $user) {
            try {
                $bot->answerCallbackQuery(text: '❌ مستخدم غير موجود', show_alert: true);
            } catch (\Throwable $e) {
            }
            return;
        }

        $this->wheelService->resetDaily($user);

        try {
            $bot->answerCallbackQuery(
                text: "♻️ تم التصفير لـ {$user->username}",
                show_alert: true,
            );
        } catch (\Throwable $e) {
        }
    }
}
