<?php

namespace App\Telegram\Conversations\Admin\Communication;

use App\Jobs\BroadcastMessageJob;
use App\Jobs\SendSingleMessageJob;
use App\Models\User;
use App\Telegram\Conversations\BaseConversation;
use App\Telegram\Keyboards\AdminsKeyboard\Communication\CommunicationKeyboard;
use App\Telegram\Screens\Admin\Communication\CommunicationScreen;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class CommunicationConversation extends BaseConversation
{
    protected ?string $target = null;
    protected ?string $broadcastText = null;
    protected ?string $singleId = null;
    protected ?string $singleText = null;

    public function start(Nutgram $bot): void
    {
        $mode = cache()->pull("comm.mode.{$bot->userId()}");

        if ($mode === 'single') {
            $this->askSingleId($bot);
            return;
        }

        $this->askTarget($bot);
    }

    // ============================================================
    //  Broadcast
    // ============================================================

    public function askTarget(Nutgram $bot): void
    {
        // 🗑️ سؤال — يُحذف
        $this->askTracked(
            $bot,
            CommunicationScreen::broadcastText(),
            parse_mode: 'HTML',
            reply_markup: CommunicationKeyboard::broadcastTargets(),
        );

        $this->next('handleTargetChoice');
    }

    public function handleTargetChoice(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;

        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        if ($data === 'comm.cancel') {
            $this->cancel($bot);
            return;
        }

        if (! str_starts_with((string) $data, 'comm.target.')) {
            $this->askTarget($bot);
            return;
        }

        $this->target = substr($data, strlen('comm.target.'));

        // ✅ يُحدّث نفس الرسالة — لا يُحذف
        $bot->editMessageText(
            text: CommunicationScreen::askForText($this->target),
            parse_mode: 'HTML',
        );

        $this->next('receiveBroadcastText');
    }

    public function receiveBroadcastText(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '' || $text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        $this->broadcastText = $text;

        // 🗑️ تأكيد وسيط — يُحذف
        $this->askTracked(
            $bot,
            CommunicationScreen::confirmText($this->target, $text),
            parse_mode: 'HTML',
            reply_markup: CommunicationKeyboard::confirm(),
        );

        $this->next('handleConfirm');
    }

    // ============================================================
    //  Single
    // ============================================================

    public function askSingleId(Nutgram $bot): void
    {
        // 🗑️ سؤال — يُحذف
        $this->askTracked(
            $bot,
            CommunicationScreen::singleAskId(),
            parse_mode: 'HTML',
        );

        $this->next('receiveSingleId');
    }

    public function receiveSingleId(Nutgram $bot): void
    {
        $id = trim((string) ($bot->message()->text ?? ''));

        if ($id === '/cancel') {
            $this->cancel($bot);
            return;
        }

        if (! ctype_digit($id)) {
            $this->askTracked($bot, '⚠️ أرسل رقم Telegram ID صالح.');
            return;
        }

        if (! User::where('telegram_id', (int) $id)->exists()) {
            $this->askTracked(
                $bot,
                "⚠️ لا يوجد مستخدم بـ ID: <code>{$id}</code>",
                parse_mode: 'HTML',
            );
            return;
        }

        $this->singleId = $id;

        // 🗑️ سؤال — يُحذف
        $this->askTracked(
            $bot,
            CommunicationScreen::singleAskText($id),
            parse_mode: 'HTML',
        );

        $this->next('receiveSingleText');
    }

    public function receiveSingleText(Nutgram $bot): void
    {
        $text = trim((string) ($bot->message()->text ?? ''));

        if ($text === '' || $text === '/cancel') {
            $this->cancel($bot);
            return;
        }

        $this->singleText = $text;

        // 🗑️ تأكيد وسيط — يُحذف
        $this->askTracked(
            $bot,
            CommunicationScreen::singleConfirm($this->singleId, $text),
            parse_mode: 'HTML',
            reply_markup: CommunicationKeyboard::confirm(),
        );

        $this->next('handleConfirm');
    }

    // ============================================================
    //  Confirm
    // ============================================================

    public function handleConfirm(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;

        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }

        if ($data === 'comm.cancel') {
            $this->cancel($bot);
            return;
        }

        if ($data !== 'comm.confirm') {
            return;
        }

        if ($this->target !== null && $this->broadcastText !== null) {
            $count = CommunicationScreen::targetCount($this->target);

            BroadcastMessageJob::dispatch(
                $this->target,
                $this->broadcastText,
                (int) $bot->userId(),
            );

            // ✅ يُحدّث نفس الرسالة — نجاح البث
            $bot->editMessageText(
                text: CommunicationScreen::startedText($this->target, $count),
                parse_mode: 'HTML',
                reply_markup: CommunicationKeyboard::main(),
            );

            $this->endAndClean($bot);
            return;
        }

        if ($this->singleId !== null && $this->singleText !== null) {
            SendSingleMessageJob::dispatch(
                $this->singleId,
                $this->singleText,
                (int) $bot->userId(),
            );

            // ✅ يُحدّث نفس الرسالة — نجاح الإرسال
            $bot->editMessageText(
                text: CommunicationScreen::singleSent($this->singleId),
                parse_mode: 'HTML',
                reply_markup: CommunicationKeyboard::main(),
            );

            $this->endAndClean($bot);
            return;
        }

        $this->start($bot);
    }

    public function cancel(Nutgram $bot): void
    {
        // ✅ إلغاء — يبقى + زر رجوع
        $this->keep(
            $bot,
            '❌ تم الإلغاء.',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '⬅️ رجوع للتواصل',
                        callback_data: 'admin.communication',
                    ),
                ),
        );

        $this->endAndClean($bot);
    }
}
