<?php

namespace App\Observers;

use App\Models\User;
use App\Services\PaymentGateway\Drivers\DepositVerificationService;

class UserObserver
{
    public function saved(User $user): void
    {
        // إذا تغيّر دور المسؤول → أبطِل الكاش
        if ($user->isDirty(['is_admin', 'is_super_admin'])) {
            DepositVerificationService::forgetSystemAdminCache();
        }
    }

    public function deleted(User $user): void
    {
        if ($user->is_admin) {
            DepositVerificationService::forgetSystemAdminCache();
        }
    }
}
