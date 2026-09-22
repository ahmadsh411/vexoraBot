<?php

namespace App\Telegram\Handlers\User;

use App\Models\Transaction;
use App\Models\User;
use App\Telegram\Keyboards\User\UserTransactionsKeyboard;
use App\Telegram\Screens\User\UserTransactionsScreen;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class UserTransactionsHandler
{
    private const PER_PAGE = 10;

    // ============================================================
    //  📜 القائمة الرئيسية
    // ============================================================

    public function index(Nutgram $bot): void
    {
        $user = $this->getUser($bot);

        if (! $user) {
            return;
        }

        $this->safeAnswer($bot);
        $this->safeEdit(
            $bot,
            UserTransactionsScreen::main($user),
            UserTransactionsKeyboard::main(),
        );
    }

    // ============================================================
    //  📄 قائمة العمليات
    // ============================================================

    public function list(Nutgram $bot): void
    {
        $user = $this->getUser($bot);

        if (! $user) {
            return;
        }

        $this->safeAnswer($bot);

        // ✅ استخراج filter + page
        $data = $bot->callbackQuery()?->data ?? '';
        $parts = explode('.', $data);
        $filter = $parts[3] ?? 'all';
        $page = (int) ($parts[4] ?? 1);

        if (! in_array($filter, ['all', 'deposits', 'withdrawals', 'exchanges', 'pending', 'completed', 'rejected'], true)) {
            $filter = 'all';
        }

        $page = max(1, $page);

        $transactions = $this->getFiltered($user, $filter, $page);
        $total = $this->countFiltered($user, $filter);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        $this->safeEdit(
            $bot,
            UserTransactionsScreen::list($user, $filter, $page),
            UserTransactionsKeyboard::list($filter, $page, $totalPages, $transactions),
        );
    }

    // ============================================================
    //  🧾 تفاصيل عملية
    // ============================================================

    public function show(Nutgram $bot, string $id): void
    {
        $user = $this->getUser($bot);

        if (! $user) {
            return;
        }

        $this->safeAnswer($bot);

        $transaction = Transaction::where('user_id', $user->id)->find((int) $id);

        if (! $transaction) {
            $this->safeAlert($bot, 'العملية غير موجودة.');
            return;
        }

        $this->safeEdit(
            $bot,
            UserTransactionsScreen::details($transaction),
            UserTransactionsKeyboard::details(),
        );
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function getUser(Nutgram $bot): ?User
    {
        return User::where('telegram_id', $bot->userId())->first();
    }

    private function getFiltered(User $user, string $filter, int $page)
    {
        $query = Transaction::where('user_id', $user->id)->latestFirst();

        $query = match ($filter) {
            'deposits'    => $query->deposits(),
            'withdrawals' => $query->withdrawals(),
            'exchanges'   => $query->ofType(Transaction::TYPE_EXCHANGE),
            'pending'     => $query->pending(),
            'completed'   => $query->completed(),
            'rejected'    => $query->rejected(),
            default       => $query,
        };

        return $query->skip(($page - 1) * self::PER_PAGE)->take(self::PER_PAGE)->get();
    }

    private function countFiltered(User $user, string $filter): int
    {
        $query = Transaction::where('user_id', $user->id);

        return match ($filter) {
            'deposits'    => $query->deposits()->count(),
            'withdrawals' => $query->withdrawals()->count(),
            'exchanges'   => $query->ofType(Transaction::TYPE_EXCHANGE)->count(),
            'pending'     => $query->pending()->count(),
            'completed'   => $query->completed()->count(),
            'rejected'    => $query->rejected()->count(),
            default       => $query->count(),
        };
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

            $bot->sendMessage(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard,
            );
        }
    }

    private function safeAnswer(Nutgram $bot): void
    {
        try {
            $bot->answerCallbackQuery();
        } catch (\Throwable $e) {
        }
    }

    private function safeAlert(Nutgram $bot, string $text): void
    {
        try {
            $bot->answerCallbackQuery(text: $text, show_alert: true);
        } catch (\Throwable $e) {
        }
    }
}
