<?php

namespace App\Services;

use App\Jobs\NotifyUserJob;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserNotificationService
{
    /**
     * إرسال لكل المستخدمين (غير الأدمن).
     */
    public function notifyAllUsers(string $text): int
    {
        $users = User::where('is_active', true)
            ->whereNotNull('telegram_id')
            ->where('is_admin', false)
            ->get();

        foreach ($users as $user) {
            NotifyUserJob::dispatch($user->id, $text);
        }

        Log::info('Dispatched notifications', ['count' => $users->count()]);

        return $users->count();
    }

    public function notifyUser(int $userId, string $text): bool
    {
        $user = User::find($userId);

        if (! $user || ! $user->telegram_id) {
            return false;
        }

        NotifyUserJob::dispatch($userId, $text);

        return true;
    }

    public function notifyUsers(array $userIds, string $text): int
    {
        foreach ($userIds as $userId) {
            NotifyUserJob::dispatch((int) $userId, $text);
        }

        return count($userIds);
    }
}
