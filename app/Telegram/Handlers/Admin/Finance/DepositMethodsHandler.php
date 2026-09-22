<?php

namespace App\Telegram\Handlers\Admin\Finance;

use App\Models\DepositMethod;
use App\Services\NotificationService;
use App\Telegram\Conversations\Admin\Finance\CreateDepositMethodConversation;
use App\Telegram\Conversations\Admin\Finance\EditDepositMethodConversation;
use App\Telegram\Keyboards\AdminsKeyboard\Finance\DepositMethodsKeyboard;
use App\Telegram\Screens\Admin\Finance\DepositMethodsScreen;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class DepositMethodsHandler
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    // ============================================================
    //  القائمة الرئيسية
    // ============================================================
    public function index(Nutgram $bot): void
    {
        $bot->answerCallbackQuery();
        $this->safeEdit($bot, DepositMethodsScreen::listText(), DepositMethodsKeyboard::list());
    }

    // ============================================================
    //  عرض التفاصيل
    // ============================================================
    public function show(Nutgram $bot, string $id): void
    {
        $bot->answerCallbackQuery();
        $method = DepositMethod::find((int) $id);

        if (! $method) {
            $bot->sendMessage(text: '❌ الطريقة غير موجودة.');
            return;
        }

        $this->safeEdit(
            $bot,
            DepositMethodsScreen::detailsText($method),
            DepositMethodsKeyboard::details($method),
        );
    }

    // ============================================================
    //  تفعيل/تعطيل
    // ============================================================
    public function toggle(Nutgram $bot, string $id): void
    {
        $method = DepositMethod::find((int) $id);

        if (! $method) {
            $bot->answerCallbackQuery(text: '❌ غير موجودة.', show_alert: true);
            return;
        }

        $wasActive = $method->is_active;

        $method->update(['is_active' => ! $wasActive]);
        $method->refresh();

        $action = $method->is_active ? 'activate' : 'deactivate';

        try {
            $this->notificationService->notifyDepositMethodChange(
                bot: $bot,
                action: $action,
                method: $method,
                adminId: (int) $bot->userId(),
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about toggle', [
                'method_id' => $method->id,
                'action'    => $action,
                'error'     => $e->getMessage(),
            ]);
        }

        try {
            $this->notificationService->notifyAllUsersDepositMethodChange(
                bot: $bot,
                action: $action,
                method: $method,
            );

            Log::info('Notified users about deposit method toggle', [
                'method_id' => $method->id,
                'action'    => $action,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify users about toggle', [
                'method_id' => $method->id,
                'action'    => $action,
                'error'     => $e->getMessage(),
            ]);
        }

        $bot->answerCallbackQuery(
            text: $method->is_active ? '🟢 تم التفعيل' : '🔴 تم التعطيل',
            show_alert: true,
        );

        $this->show($bot, (string) $method->id);
    }

    // ============================================================
    //  تأكيد الحذف
    // ============================================================
    public function delete(Nutgram $bot, string $id): void
    {
        $bot->answerCallbackQuery();
        $method = DepositMethod::find((int) $id);

        if (! $method) {
            $bot->sendMessage(text: '❌ غير موجودة.');
            return;
        }

        $this->safeEdit(
            $bot,
            DepositMethodsScreen::deleteConfirmText($method),
            DepositMethodsKeyboard::confirmDelete($method),
        );
    }

    // ============================================================
    //  تنفيذ الحذف
    // ============================================================
    public function deleteConfirm(Nutgram $bot, string $id): void
    {
        $method = DepositMethod::find((int) $id);

        if (! $method) {
            $bot->answerCallbackQuery(text: '❌ غير موجودة.', show_alert: true);
            return;
        }

        $methodData = $method->toArray();

        try {
            $this->notificationService->notifyDepositMethodChange(
                bot: $bot,
                action: 'delete',
                method: $methodData,
                adminId: (int) $bot->userId(),
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify channel about delete', [
                'method_id' => $method->id,
                'error'     => $e->getMessage(),
            ]);
        }

        try {
            $this->notificationService->notifyAllUsersDepositMethodChange(
                bot: $bot,
                action: 'delete',
                method: $methodData,
            );

            Log::info('Notified users about deposit method delete', [
                'method_id' => $method->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify users about delete', [
                'method_id' => $method->id,
                'error'     => $e->getMessage(),
            ]);
        }

        $method->delete();

        $bot->answerCallbackQuery(text: '🗑️ تم الحذف', show_alert: true);
        $this->index($bot);
    }

    // ============================================================
    //  إنشاء طريقة جديدة
    // ============================================================
    public function create(Nutgram $bot): void
    {
        $bot->answerCallbackQuery();

        CreateDepositMethodConversation::begin(
            bot: $bot,
            userId: $bot->userId(),
            chatId: $bot->chatId(),
        );
    }

    // ============================================================
    //  Edit Actions
    // ============================================================

    public function editName(Nutgram $bot, string $id): void
    {
        $this->startEdit($bot, $id, 'name');
    }

    public function editIcon(Nutgram $bot, string $id): void
    {
        $this->startEdit($bot, $id, 'icon');
    }

    public function editAccount(Nutgram $bot, string $id): void
    {
        $this->startEdit($bot, $id, 'account_number');
    }

    public function editAccountName(Nutgram $bot, string $id): void
    {
        $this->startEdit($bot, $id, 'account_name');
    }

    public function editInstructions(Nutgram $bot, string $id): void
    {
        $this->startEdit($bot, $id, 'instructions');
    }

    public function editLimits(Nutgram $bot, string $id): void
    {
        $this->startEdit($bot, $id, 'limits');
    }

    public function editCommission(Nutgram $bot, string $id): void
    {
        $this->startEdit($bot, $id, 'commission');
    }

    // ✅ جديد: GSM
    public function editReceiverGsm(Nutgram $bot, string $id): void
    {
        $this->startEdit($bot, $id, 'receiver_gsm');
    }

    // ✅ جديد: Address
    public function editReceiverAddress(Nutgram $bot, string $id): void
    {
        $this->startEdit($bot, $id, 'receiver_address');
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function startEdit(Nutgram $bot, string $id, string $field): void
    {
        $method = DepositMethod::find((int) $id);

        if (! $method) {
            $bot->answerCallbackQuery(text: '❌ غير موجودة.', show_alert: true);
            return;
        }

        $bot->answerCallbackQuery();

        $cacheKey = 'edit_dm_' . $bot->userId() . '_' . $bot->chatId();
        Cache::put($cacheKey, [
            'method_id' => $method->id,
            'field'     => $field,
        ], now()->addMinutes(10));

        EditDepositMethodConversation::begin(
            bot: $bot,
            userId: $bot->userId(),
            chatId: $bot->chatId(),
        );
    }

    private function safeEdit(Nutgram $bot, string $text, $keyboard): void
    {
        try {
            $bot->editMessageText(text: $text, parse_mode: 'HTML', reply_markup: $keyboard);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not modified')) {
                return;
            }
            $bot->sendMessage(text: $text, parse_mode: 'HTML', reply_markup: $keyboard);
        }
    }
}
