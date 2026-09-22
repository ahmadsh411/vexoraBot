<?php

namespace App\Telegram\Handlers\Admin\Referrals;

use App\Models\ReferralCycle;
use App\Services\NotificationService;
use App\Services\ReferralCycleService;
use App\Telegram\Keyboards\AdminsKeyboard\Referrals\ReferralCyclesKeyboard;
use App\Telegram\Screens\Admin\Referrals\ReferralCyclesScreen;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class ReferralCyclesHandler
{
    private const CALLBACK_TEXT_MAX = 200;

    // ============================================================
    //  📅 index
    // ============================================================

    public function index(Nutgram $bot): void
    {
        $this->answer($bot);

        $this->safeEdit(
            $bot,
            ReferralCyclesScreen::text(),
            ReferralCyclesKeyboard::make(),
        );
    }

    // ============================================================
    //  👁️ show
    // ============================================================

    public function show(Nutgram $bot): void
    {
        $this->answer($bot);

        $id = $this->extractId($bot);

        if (! $id) {
            $this->answer($bot, '❌ معرّف غير صالح.', true);
            return;
        }

        $cycle = ReferralCycle::find($id);

        if (! $cycle) {
            $this->answer($bot, '❌ الدورة غير موجودة.', true);
            return;
        }

        $this->safeEdit(
            $bot,
            ReferralCyclesScreen::detailsText($cycle),
            ReferralCyclesKeyboard::details($cycle->id),
        );
    }

    // ============================================================
    //  📜 list
    // ============================================================

    public function list(Nutgram $bot): void
    {
        $this->answer($bot);

        $this->safeEdit(
            $bot,
            ReferralCyclesScreen::listText(),
            ReferralCyclesKeyboard::list(),
        );
    }

    // ============================================================
    //  🏆 rewards
    // ============================================================

    public function rewards(Nutgram $bot): void
    {
        $this->answer($bot);

        $id = $this->extractId($bot);

        if (! $id) {
            $this->answer($bot, '❌ معرّف غير صالح.', true);
            return;
        }

        $cycle = ReferralCycle::find($id);

        if (! $cycle) {
            $this->answer($bot, '❌ الدورة غير موجودة.', true);
            return;
        }

        $this->safeEdit(
            $bot,
            ReferralCyclesScreen::rewardsText($cycle),
            ReferralCyclesKeyboard::rewards($cycle->id),
        );
    }

    // ============================================================
    //  🔒 close
    // ============================================================

    public function close(Nutgram $bot): void
    {
        $this->answer($bot);

        $id = $this->extractId($bot);

        if (! $id) {
            $this->answer($bot, '❌ معرّف غير صالح.', true);
            return;
        }

        $cycle = ReferralCycle::find($id);

        if (! $cycle) {
            $this->answer($bot, '❌ الدورة غير موجودة.', true);
            return;
        }

        if (! $cycle->isOpen()) {
            $this->answer($bot, '⚠️ الدورة مغلقة مسبقاً.', true);
            return;
        }

        $this->safeEdit(
            $bot,
            ReferralCyclesScreen::confirmCloseText($cycle),
            ReferralCyclesKeyboard::confirmClose($cycle->id),
        );
    }

    // ============================================================
    //  🔒 closeConfirm
    // ============================================================

    public function closeConfirm(Nutgram $bot): void
    {
        $id = $this->extractId($bot);

        if (! $id) {
            $this->answer($bot, '❌ معرّف غير صالح.', true);
            return;
        }

        $cycle = ReferralCycle::find($id);

        if (! $cycle || ! $cycle->isOpen()) {
            $this->answer($bot, '⚠️ الدورة غير متاحة.', true);
            return;
        }

        try {
            $service = app(ReferralCycleService::class);
            $result = $service->processCycle($cycle);

            Log::info('Cycle closed manually by admin', [
                'admin_id'        => $bot->userId(),
                'cycle_id'        => $cycle->id,
                'referrers_count' => $result['referrers_count'],
                'total_rewards'   => $result['total_rewards'],
            ]);

            // ✅ إشعار قناة Transactions
            $this->notifyTransactionsChannelAboutCycleClose($bot, $cycle, $result);

            $bot->answerCallbackQuery();

            $bot->sendMessage(
                text: implode("\n", [
                    '✅ <b>تم إغلاق الدورة بنجاح</b>',
                    '━━━━━━━━━━━━━━━━━━',
                    '',
                    '📅 <b>الدورة:</b> ' . $cycle->duration_label,
                    '',
                    '📊 <b>النتائج:</b>',
                    '├── 👥 مُحيلون: <b>' . $result['referrers_count'] . '</b>',
                    '├── 💰 مكافآت: <b>' . number_format($result['total_rewards'], 2) . '</b> NSP',
                    '└── 🔥 حرق: <b>' . number_format((float) $cycle->fresh()->total_burned, 2) . '</b> NSP',
                    '',
                    '🆕 <b>دورة جديدة:</b> ' . $service->getOrCreateCurrentCycle()->duration_label,
                ]),
                parse_mode: 'HTML',
            );

            $this->safeEdit(
                $bot,
                ReferralCyclesScreen::text(),
                ReferralCyclesKeyboard::make(),
            );
        } catch (\Throwable $e) {
            Log::error('Cycle close failed', [
                'cycle_id' => $cycle->id,
                'error'    => $e->getMessage(),
            ]);

            $bot->answerCallbackQuery();

            $bot->sendMessage(
                text: implode("\n", [
                    '❌ <b>فشل إغلاق الدورة</b>',
                    '',
                    '<code>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</code>',
                ]),
                parse_mode: 'HTML',
            );
        }
    }

    // ============================================================
    //  🆕 create
    // ============================================================

    public function create(Nutgram $bot): void
    {
        $this->answer($bot);

        $existing = ReferralCycle::open()->first();

        if ($existing) {
            $this->answer($bot, '⚠️ توجد دورة مفتوحة بالفعل.', true);
            return;
        }

        try {
            $service = app(ReferralCycleService::class);
            $cycle = $service->getOrCreateCurrentCycle();

            Log::info('New cycle created manually', [
                'admin_id' => $bot->userId(),
                'cycle_id' => $cycle->id,
            ]);

            // ✅ إشعار قناة Transactions
            $this->notifyTransactionsChannelAboutCycleCreate($bot, $cycle);

            $bot->answerCallbackQuery();

            $bot->sendMessage(
                text: implode("\n", [
                    '✅ <b>تم إنشاء دورة جديدة</b>',
                    '',
                    '📅 ' . $cycle->duration_label,
                    '⏱️ الأيام: <b>' . $cycle->daysRemaining() . '</b>',
                ]),
                parse_mode: 'HTML',
            );

            $this->safeEdit(
                $bot,
                ReferralCyclesScreen::text(),
                ReferralCyclesKeyboard::make(),
            );
        } catch (\Throwable $e) {
            Log::error('Cycle creation failed', [
                'error' => $e->getMessage(),
            ]);

            $bot->answerCallbackQuery();
            $bot->sendMessage(text: '❌ فشل: ' . $e->getMessage());
        }
    }

    // ============================================================
    //  📢 إشعار قناة Transactions — إغلاق دورة
    // ============================================================

    private function notifyTransactionsChannelAboutCycleClose(
        Nutgram $bot,
        ReferralCycle $cycle,
        array $result,
    ): void {
        try {
            $text = implode("\n", [
                '🔒 <b>تم إغلاق دورة إحالات</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📅 <b>الدورة:</b> ' . $cycle->duration_label,
                '',
                '📊 <b>النتائج:</b>',
                '├── 👥 مُحيلون: <b>' . $result['referrers_count'] . '</b>',
                '├── 💰 مكافآت: <b>' . number_format($result['total_rewards'], 2) . '</b> NSP',
                '└── 🔥 حرق: <b>' . number_format((float) $cycle->fresh()->total_burned, 2) . '</b> NSP',
                '',
                '👮 <b>بواسطة:</b> <code>' . $bot->userId() . '</code>',
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            app(NotificationService::class)->notifyTransactionsChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about cycle close', [
                'cycle_id' => $cycle->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  📢 إشعار قناة Transactions — إنشاء دورة
    // ============================================================

    private function notifyTransactionsChannelAboutCycleCreate(
        Nutgram $bot,
        ReferralCycle $cycle,
    ): void {
        try {
            $text = implode("\n", [
                '🆕 <b>دورة إحالات جديدة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📅 <b>الدورة:</b> ' . $cycle->duration_label,
                '⏱️ <b>الأيام المتبقية:</b> ' . $cycle->daysRemaining(),
                '',
                '👮 <b>بواسطة:</b> <code>' . $bot->userId() . '</code>',
                '📅 ' . now()->format('Y-m-d H:i'),
            ]);

            app(NotificationService::class)->notifyTransactionsChannel($bot, $text);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about cycle create', [
                'cycle_id' => $cycle->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function extractId(Nutgram $bot): ?int
    {
        $data = $bot->callbackQuery()?->data;

        if (! $data) {
            return null;
        }

        preg_match('/\.(\d+)(?:\.|$)/', $data, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }

    private function answer(Nutgram $bot, ?string $text = null, bool $showAlert = false): void
    {
        try {
            if ($text === null) {
                $bot->answerCallbackQuery();
                return;
            }

            $text = mb_substr($text, 0, self::CALLBACK_TEXT_MAX);

            $bot->answerCallbackQuery(text: $text, show_alert: $showAlert);
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
            } catch (\Throwable $inner) {
                Log::error('sendMessage failed', ['error' => $inner->getMessage()]);
            }
        }
    }
}
