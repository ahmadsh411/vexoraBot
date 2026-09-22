<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Services\Concerns\HasLockingHelpers;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class UserService
{
    use HasLockingHelpers;

    // ============================================================
    //  📖 القراءة
    // ============================================================

    public function getAll(int $perPage = 10): LengthAwarePaginator
    {
        return User::query()
            ->latest('id')
            ->paginate($perPage);
    }

    public function findById(int $id): ?User
    {
        return User::query()->find($id);
    }

    public function findByTelegramId(int $telegramId): ?User
    {
        return User::query()
            ->where('telegram_id', $telegramId)
            ->first();
    }

    public function findByUsername(string $username): ?User
    {
        return User::query()
            ->where('username', $username)
            ->first();
    }

    // ============================================================
    //  ✨ الإنشاء (مُحدَّث)
    // ============================================================

    public function create(array $data): User
    {
        return $this->runInTransaction(function () use ($data) {
            // ✅ 1. التحقق من كلمة المرور
            $password = $data['password'] ?? null;

            if (empty($password)) {
                throw new \InvalidArgumentException('كلمة المرور مطلوبة');
            }

            // ✅ 2. تشفير كلمة المرور
            $encryptionService = app(PasswordEncryptionService::class);
            $encrypted = $encryptionService->encrypt($password);

            // ✅ 3. إنشاء المستخدم — INSERT واحد
            $user = User::create([
                'telegram_id'         => $data['telegram_id'],
                'telegram_username'   => $data['telegram_username'] ?? null,
                'username'            => $data['username'],
                'password_encrypted'  => $encrypted['encrypted'],
                'password_key_id'     => $encrypted['key_id'],
                'password_changed_at' => now(),
                'first_name'          => $data['first_name'] ?? null,
                'last_name'           => $data['last_name'] ?? null,
                'ichancy_player_id'   => $data['ichancy_player_id'] ?? null,
                'is_active'           => $data['is_active'] ?? true,
                'is_admin'            => $data['is_admin'] ?? false,
                'is_super_admin'      => $data['is_super_admin'] ?? false,
                'last_login_at'       => $data['last_login_at'] ?? null,
            ]);

            // ✅ 4. إنشاء المحفظة
            Wallet::create([
                'user_id'            => $user->id,
                'type'               => Wallet::TYPE_USER,
                'balance_nsp'        => 0,
                'balance_usd'        => 0,
                'total_deposit_nsp'  => 0,
                'total_deposit_usd'  => 0,
                'total_withdraw_nsp' => 0,
                'total_withdraw_usd' => 0,
                'is_active'          => true,
                'is_frozen'          => false,
            ]);

            Log::info('User + Wallet created', [
                'user_id'  => $user->id,
                'username' => $user->username,
            ]);

            return $user->fresh();
        });
    }

    // ============================================================
    //  ✏️ التحديث
    // ============================================================

    public function update(User $user, array $data): User
    {
        $updateData = [];

        $allowed = [
            'telegram_username',
            'username',
            'ichancy_player_id',
            'first_name',
            'last_name',
            'is_active',
            'is_admin',
            'is_super_admin',
            'last_login_at',
            'admin_seen_at',
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        // ✅ تحديث كلمة المرور إن وُجدت
        if (! empty($data['password'])) {
            $user->setPassword($data['password']);
            $user->save();
        }

        if (! empty($updateData)) {
            $user->update($updateData);
        }

        return $user->refresh();
    }

    // ============================================================
    //  🗑️ الحذف
    // ============================================================

    public function delete(User $user): bool
    {
        return (bool) $user->delete();
    }

    public function restore(User $user): bool
    {
        return (bool) $user->restore();
    }

    public function forceDelete(User $user): bool
    {
        return (bool) $user->forceDelete();
    }

    // ============================================================
    //  ⚡ التفعيل / الإيقاف
    // ============================================================

    public function activate(User $user): bool
    {
        return $user->update(['is_active' => true]);
    }

    public function deactivate(User $user): bool
    {
        return $user->update(['is_active' => false]);
    }

    // ============================================================
    //  🔍 التحقق
    // ============================================================

    public function existsByTelegramId(int $telegramId): bool
    {
        return User::query()
            ->where('telegram_id', $telegramId)
            ->exists();
    }

    public function existsByUsername(string $username): bool
    {
        return User::query()
            ->where('username', $username)
            ->exists();
    }

    // ============================================================
    //  🔑 كلمة المرور
    // ============================================================

    public function getPassword(User $user, string $reason = '', ?int $accessedBy = null): ?string
    {
        return $user->getPassword($reason, $accessedBy);
    }

    public function checkPassword(User $user, string $plainPassword): bool
    {
        return $user->checkPassword($plainPassword);
    }
}
