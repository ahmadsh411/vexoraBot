<?php

namespace App\Telegram\Handlers\Admin\Users;

use App\Models\User;
use App\Telegram\Keyboards\AdminsKeyboard\Users\UserReferralKeyboard;
use App\Telegram\Screens\Admin\Users\UserReferralScreen;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class UserReferralHandler
{
    /**
     * عرض صفحة إحالات المستخدم.
     *
     * pattern: admin.users.referrals.{id}
     */
    public function index(Nutgram $bot, ?string $id = null): void
    {
        $bot->answerCallbackQuery();

        $id = $this->resolveId($id, $bot);

        if (! $id) {
            $bot->sendMessage(text: '❌ معرّف غير صالح.');
            return;
        }

        $user = User::find($id);

        if (! $user) {
            $bot->sendMessage(text: '❌ المستخدم غير موجود.');
            return;
        }

        $this->safeEdit(
            $bot,
            UserReferralScreen::text($user),
            UserReferralKeyboard::make($user->id),
        );
    }

    /**
     * قائمة المُحالين.
     *
     * pattern: admin.users.referrals.list.{id}
     */
    public function listReferrals(Nutgram $bot, ?string $id = null): void
    {
        $bot->answerCallbackQuery();

        $id = $this->resolveId($id, $bot);

        if (! $id) {
            $bot->sendMessage(text: '❌ معرّف غير صالح.');
            return;
        }

        $user = User::find($id);

        if (! $user) {
            $bot->sendMessage(text: '❌ المستخدم غير موجود.');
            return;
        }

        $this->safeEdit(
            $bot,
            UserReferralScreen::referralsListText($user),
            UserReferralKeyboard::referralsList($user->id),
        );
    }

    /**
     * سجل المكافآت.
     *
     * pattern: admin.users.referrals.rewards.{id}
     */
    public function rewards(Nutgram $bot, ?string $id = null): void
    {
        $bot->answerCallbackQuery();

        $id = $this->resolveId($id, $bot);

        if (! $id) {
            $bot->sendMessage(text: '❌ معرّف غير صالح.');
            return;
        }

        $user = User::find($id);

        if (! $user) {
            $bot->sendMessage(text: '❌ المستخدم غير موجود.');
            return;
        }

        $this->safeEdit(
            $bot,
            UserReferralScreen::rewardsListText($user, 'all'),
            UserReferralKeyboard::rewardsList($user->id),
        );
    }

    /**
     * المكافآت الفورية.
     *
     * pattern: admin.users.referrals.instant.{id}
     */
    public function instant(Nutgram $bot, ?string $id = null): void
    {
        $bot->answerCallbackQuery();

        $id = $this->resolveId($id, $bot);

        if (! $id) {
            $bot->sendMessage(text: '❌ معرّف غير صالح.');
            return;
        }

        $user = User::find($id);

        if (! $user) {
            $bot->sendMessage(text: '❌ المستخدم غير موجود.');
            return;
        }

        $this->safeEdit(
            $bot,
            UserReferralScreen::rewardsListText($user, 'instant'),
            UserReferralKeyboard::rewardsList($user->id),
        );
    }

    /**
     * مكافآت الدورات.
     *
     * pattern: admin.users.referrals.cycles.{id}
     */
    public function cycles(Nutgram $bot, ?string $id = null): void
    {
        $bot->answerCallbackQuery();

        $id = $this->resolveId($id, $bot);

        if (! $id) {
            $bot->sendMessage(text: '❌ معرّف غير صالح.');
            return;
        }

        $user = User::find($id);

        if (! $user) {
            $bot->sendMessage(text: '❌ المستخدم غير موجود.');
            return;
        }

        $this->safeEdit(
            $bot,
            UserReferralScreen::rewardsListText($user, 'cycle'),
            UserReferralKeyboard::rewardsList($user->id),
        );
    }

    /**
     * طلب تأكيد إعادة تعيين النوع.
     *
     * pattern: admin.users.referrals.reset-type.{id}
     */
    public function resetType(Nutgram $bot, ?string $id = null): void
    {
        $bot->answerCallbackQuery();

        $id = $this->resolveId($id, $bot);

        if (! $id) {
            $bot->sendMessage(text: '❌ معرّف غير صالح.');
            return;
        }

        $user = User::find($id);

        if (! $user) {
            $bot->sendMessage(text: '❌ المستخدم غير موجود.');
            return;
        }

        $this->safeEdit(
            $bot,
            UserReferralScreen::confirmResetTypeText($user),
            UserReferralKeyboard::confirmResetType($user->id),
        );
    }

    /**
     * تنفيذ إعادة تعيين النوع.
     *
     * pattern: admin.users.referrals.reset-type-confirm.{id}
     */
    public function resetTypeConfirm(Nutgram $bot, ?string $id = null): void
    {
        $id = $this->resolveId($id, $bot);

        if (! $id) {
            $bot->answerCallbackQuery(text: '❌ معرّف غير صالح.', show_alert: true);
            return;
        }

        $user = User::find($id);

        if (! $user) {
            $bot->answerCallbackQuery(text: '❌ المستخدم غير موجود.', show_alert: true);
            return;
        }

        $user->update([
            'referral_type'      => null,
            'referral_chosen_at' => null,
        ]);

        $bot->answerCallbackQuery(
            text: '✅ تم إعادة تعيين النوع',
            show_alert: true,
        );

        Log::info('Admin reset user referral type', [
            'admin_id' => $bot->userId(),
            'user_id'  => $user->id,
        ]);

        // ارجع إلى الصفحة الرئيسية للإحالات
        $this->index($bot, (string) $user->id);
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function resolveId(?string $id, Nutgram $bot): ?int
    {
        if ($id !== null && is_numeric($id)) {
            return (int) $id;
        }

        $data = $bot->callbackQuery()?->data;

        if (! $data) {
            return null;
        }

        preg_match('/\.(\d+)(?:\.|$)/', $data, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
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
            if (str_contains($e->getMessage(), 'message is not modified')) {
                return;
            }

            $bot->sendMessage(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
            );
        }
    }
}
