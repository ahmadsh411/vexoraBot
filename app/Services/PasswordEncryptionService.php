<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Log;

class PasswordEncryptionService
{
    private Encrypter $encrypter;
    private string $keyId;

    public function __construct()
    {
        $key = config('passwords.encryption_key');

        // ✅ تحويل المفتاح لـ bytes
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        $this->encrypter = new Encrypter($key, 'AES-256-CBC');
        $this->keyId = config('passwords.current_key_id', 'v1');
    }

    /**
     * تشفير كلمة المرور.
     */
    public function encrypt(string $plainPassword): array
    {
        return [
            'encrypted' => $this->encrypter->encryptString($plainPassword),
            'key_id'    => $this->keyId,
        ];
    }

    /**
     * فك تشفير كلمة المرور.
     */
    public function decrypt(string $encrypted, string $keyId = 'v1'): ?string
    {
        try {
            return $this->encrypter->decryptString($encrypted);
        } catch (\Throwable $e) {
            Log::error('Password decryption failed', [
                'key_id' => $keyId,
                'error'  => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * التحقق من كلمة المرور.
     */
    public function check(string $encrypted, string $plainPassword): bool
    {
        $decrypted = $this->decrypt($encrypted);

        if ($decrypted === null) {
            return false;
        }

        return hash_equals($decrypted, $plainPassword);
    }
}
