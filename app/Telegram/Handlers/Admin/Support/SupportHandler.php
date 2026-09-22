<?php

namespace App\Telegram\Handlers\Admin\Support;

use App\Models\SupportAgent;
use App\Services\SupportChannelService;
use App\Services\SubscriptionService;
use App\Telegram\Conversations\Admin\Support\AddSupportAgentConversation;
use App\Telegram\Keyboards\AdminsKeyboard\Support\SupportKeyboard;
use App\Telegram\Screens\Admin\Support\SupportScreen;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class SupportHandler
{
    // ============================================================
    //  🏠 index
    // ============================================================
    public function index(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        $this->safeEdit(
            $bot,
            SupportScreen::admin(),
            SupportKeyboard::admin(),
        );
    }

    // ============================================================
    //  👁️ showAgent
    // ============================================================
    public function showAgent(Nutgram $bot, string $id): void
    {
        $this->safeAnswer($bot);

        $agent = SupportAgent::find((int) $id);

        if (! $agent) return;

        $this->safeEdit(
            $bot,
            SupportScreen::agentDetails($agent),
            SupportKeyboard::agentDetails($agent),
        );
    }

    // ============================================================
    //  ⚡ toggleAgent — مع إزالة من القنوات عند التعطيل
    // ============================================================
    public function toggleAgent(Nutgram $bot, string $id): void
    {
        $this->safeAnswer($bot);

        $agent = SupportAgent::find((int) $id);

        if (! $agent) return;

        $wasActive = $agent->is_active;

        $agent->update(['is_active' => ! $wasActive]);

        // ✅ 1. إذا تم التعطيل → أزله من القنوات + احذف الرسالة
        if ($wasActive && ! $agent->is_active) {
            $this->removeFromChannels($agent);
            $this->deleteJoinMessage($agent);

            try {
                $bot->answerCallbackQuery(
                    text: '🔴 تم التعطيل + الإزالة من القنوات',
                    show_alert: true,
                );
            } catch (\Throwable $e) {
            }
        }

        // ✅ 2. إذا تم التفعيل → أرسل رسالة الاشتراك
        if (! $wasActive && $agent->is_active) {
            $sent = app(SupportChannelService::class)->sendJoinMessage($agent);

            try {
                $bot->answerCallbackQuery(
                    text: $sent
                        ? '🟢 تم التفعيل + إرسال الروابط'
                        : '🟢 تم التفعيل (تعذّر إرسال الروابط)',
                    show_alert: true,
                );
            } catch (\Throwable $e) {
            }
        }

        $this->showAgent($bot, $id);
    }

    // ============================================================
    //  🗑️ confirmDelete
    // ============================================================
    public function confirmDelete(Nutgram $bot, string $id): void
    {
        $this->safeAnswer($bot);

        $agent = SupportAgent::find((int) $id);

        if (! $agent) return;

        $this->safeEdit(
            $bot,
            SupportScreen::confirmDelete($agent),
            SupportKeyboard::confirmDelete($agent),
        );
    }

    // ============================================================
    //  🗑️ deleteAgent — مع إزالة من القنوات
    // ============================================================
    public function deleteAgent(Nutgram $bot, string $id): void
    {
        $this->safeAnswer($bot);

        $agent = SupportAgent::find((int) $id);

        if (! $agent) return;

        // ✅ 1. أزله من القنوات أولاً
        $this->removeFromChannels($agent);

        // ✅ 2. احذف رسالة الاشتراك من محادثته
        $this->deleteJoinMessage($agent);

        // ✅ 3. احذفه من DB
        $agent->delete();

        $this->index($bot);
    }

    // ============================================================
    //  ➕ create
    // ============================================================
    public function create(Nutgram $bot): void
    {
        $this->safeAnswer($bot);

        AddSupportAgentConversation::begin($bot);
    }

    // ============================================================
    //  ✏️ editName
    // ============================================================
    public function editName(Nutgram $bot, string $id): void
    {
        $this->safeAnswer($bot);

        $agent = SupportAgent::find((int) $id);

        if (! $agent) return;

        Cache::put("support.edit.agent.{$bot->userId()}", (int) $id, now()->addMinutes(10));
        Cache::put("support.edit.field.{$bot->userId()}", 'name', now()->addMinutes(10));

        \App\Telegram\Conversations\Admin\Support\EditSupportAgentConversation::begin($bot);
    }

    // ============================================================
    //  ✏️ editUsername
    // ============================================================
    public function editUsername(Nutgram $bot, string $id): void
    {
        $this->safeAnswer($bot);

        $agent = SupportAgent::find((int) $id);

        if (! $agent) return;

        Cache::put("support.edit.agent.{$bot->userId()}", (int) $id, now()->addMinutes(10));
        Cache::put("support.edit.field.{$bot->userId()}", 'username', now()->addMinutes(10));

        \App\Telegram\Conversations\Admin\Support\EditSupportAgentConversation::begin($bot);
    }

    // ============================================================
    //  ✅ joinConfirm
    // ============================================================
    public function joinConfirm(Nutgram $bot, string $id): void
    {
        $this->safeAnswer($bot);

        $agent = SupportAgent::find((int) $id);

        if (! $agent) return;

        // ✅ التحقق من الاشتراك في القنوات الثلاثة
        if (! $agent->hasTelegramId()) {
            try {
                $bot->sendMessage(
                    text: '⚠️ لا يمكن التحقق — لم يتم ربط حسابك بعد.',
                    chat_id: $bot->chatId(),
                );
            } catch (\Throwable $e) {
            }
            return;
        }

        try {
            $service = app(SubscriptionService::class);
            $missing = $service->getMissingAdminChannels($agent->telegram_id);

            // ─── إذا نقصت قناة ───
            if (! empty($missing)) {
                $service->sendMissingChannelsMessage($bot, $missing, $agent->id);

                Log::info('Support: joinConfirm — missing channels', [
                    'agent_id' => $agent->id,
                    'missing'  => array_keys($missing),
                ]);

                return;
            }

            // ─── ✅ مشترك في الكل ───
            $agent->update(['channels_joined_at' => now()]);

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

            Log::info('Support: joinConfirm — success', [
                'agent_id' => $agent->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Support: joinConfirm failed', [
                'agent_id' => $agent->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    //  🔕 noop
    // ============================================================
    public function noop(Nutgram $bot): void
    {
        $this->safeAnswer($bot);
    }

    // ============================================================
    //  🗑️ إزالة الداعم من القنوات (Helper)
    // ============================================================
    private function removeFromChannels(SupportAgent $agent): void
    {
        if (! $agent->hasTelegramId()) {
            Log::info('SupportHandler: cannot remove — no telegram_id', [
                'agent_id' => $agent->id,
            ]);
            return;
        }

        try {
            $results = app(SupportChannelService::class)->removeFromAllChannels($agent);

            Log::info('SupportHandler: agent removed from channels', [
                'agent_id' => $agent->id,
                'results'  => $results,
            ]);
        } catch (\Throwable $e) {
            Log::warning('SupportHandler: failed to remove agent from channels', [
                'agent_id' => $agent->id,
                'error'    => $e->getMessage(),
            ]);
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

    private function safeEdit(Nutgram $bot, string $text, $keyboard): void
    {
        try {
            $bot->editMessageText(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
                disable_web_page_preview: true,
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
                    disable_web_page_preview: true,
                );
            } catch (\Throwable $e2) {
            }
        }
    }

    // ============================================================
    //  🗑️ حذف رسالة الاشتراك من محادثة الداعم
    // ============================================================
    private function deleteJoinMessage(SupportAgent $agent): void
    {
        if (! $agent->hasTelegramId()) {
            return;
        }

        try {
            app(SupportChannelService::class)->deleteJoinMessage($agent);
        } catch (\Throwable $e) {
            Log::warning('SupportHandler: failed to delete join message', [
                'agent_id' => $agent->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
