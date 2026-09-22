<?php

// app/Services/GiftCodeService.php
namespace App\Services;

use App\Models\GiftCode;
use App\Models\GiftRedemption;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class GiftCodeService
{
    /**
     * إنشاء كود هدية جديد
     */
    /**
     * إنشاء كود هدية جديد
     *
     * @param  int  $secondsValid  الصلاحية بالثواني (60 = دقيقة، 3600 = ساعة، 86400 = يوم)
     */
    public function createCode(
        int $adminUserId,
        float $value,
        string $currency = 'NSP',
        int $secondsValid = 86400,   // ← افتراضي: 24 ساعة = 86400 ثانية
        int $maxUses = 1,
        ?string $note = null,
        ?int $channelId = null
    ): GiftCode {
        return GiftCode::create([
            'code'       => GiftCode::generateUniqueCode(),
            'value'      => $value,
            'currency'   => strtoupper($currency),
            'max_uses'   => $maxUses,
            'used_count' => 0,
            'status'     => 'active',
            'created_by' => $adminUserId,
            'channel_id' => $channelId,
            'expires_at' => Carbon::now()->addSeconds($secondsValid),
            'note'       => $note,
        ]);
    }

    /**
     * 🔥 استبدال الكود (ذري + آمن مع المحافظ + حماية من الأدمن + إشعار قناة العمليات)
     *
     * @return array{ok: bool, msg: string, value?: float, currency?: string, gift?: GiftCode}
     */
    public function redeem(int $userId, string $codeText): array
    {
        try {
            $result = DB::transaction(function () use ($userId, $codeText) {

                // ============================================================
                //  🛡️ طبقة الحماية 1: منع الأدمن والسوبر أدمن
                // ============================================================
                $user = User::find($userId);

                if (! $user) {
                    return ['ok' => false, 'msg' => '❌ المستخدم غير موجود.'];
                }

                if ($user->is_admin || $user->is_super_admin) {
                    Log::warning('GiftCode redeem blocked: admin attempted', [
                        'user_id'  => $userId,
                        'username' => $user->username,
                        'is_admin' => $user->is_admin,
                        'is_super' => $user->is_super_admin,
                        'code'     => $codeText,
                    ]);

                    return [
                        'ok'  => false,
                        'msg' => "🚫 عذراً، هذه الميزة مخصصة للمستخدمين فقط.\n\n"
                            . "أنت " . ($user->is_super_admin ? 'سوبر أدمن' : 'أدمن')
                            . "، ولا يمكنك استخدام أكواد الهدايا.",
                    ];
                }

                // ============================================================
                //  🔍 1. قفل الكود لمنع التزاحم
                // ============================================================
                $gift = GiftCode::where('code', $codeText)
                    ->lockForUpdate()
                    ->first();

                if (! $gift) {
                    return ['ok' => false, 'msg' => '❌ هذا الكود غير موجود.'];
                }

                if ($gift->status === 'used') {
                    return ['ok' => false, 'msg' => '❌ عذراً، تم استخدام هذا الكود من قبل شخص آخر.'];
                }

                if ($gift->status === 'disabled') {
                    return ['ok' => false, 'msg' => '🚫 هذا الكود معطّل من الإدارة.'];
                }

                if ($gift->is_expired) {
                    $gift->update(['status' => 'expired']);
                    return ['ok' => false, 'msg' => '⏰ انتهت صلاحية هذا الكود.'];
                }

                // ============================================================
                //  🛡️ حماية إضافية: نفس المستخدم لا يستخدم نفس الكود مرتين
                // ============================================================
                $already = GiftRedemption::where('gift_code_id', $gift->id)
                    ->where('user_id', $userId)
                    ->exists();

                if ($already) {
                    return ['ok' => false, 'msg' => '⚠️ لقد استخدمت هذا الكود مسبقاً.'];
                }

                // ============================================================
                //  🔒 3. التحديث الذري للكود
                // ============================================================
                $updated = GiftCode::where('id', $gift->id)
                    ->where('status', 'active')
                    ->whereColumn('used_count', '<', 'max_uses')
                    ->update([
                        'used_count' => DB::raw('used_count + 1'),
                        'status'     => DB::raw(
                            "CASE WHEN used_count + 1 >= max_uses THEN 'used' ELSE 'active' END"
                        ),
                    ]);

                if ($updated === 0) {
                    return ['ok' => false, 'msg' => '⚡ للأسف، شخص آخر استخدم الكود قبلك بلحظة!'];
                }

                // ============================================================
                //  💰 4. جلب محفظة المستخدم وقفلها
                // ============================================================
                $wallet = Wallet::where('user_id', $userId)
                    ->where('type', 'user')
                    ->lockForUpdate()
                    ->first();

                if (! $wallet) {
                    Wallet::create([
                        'user_id' => $userId,
                        'type'    => 'user',
                    ]);

                    $wallet = Wallet::where('user_id', $userId)
                        ->where('type', 'user')
                        ->lockForUpdate()
                        ->first();
                }

                if ($wallet->is_frozen) {
                    throw new RuntimeException('🧊 محفظتك مجمّدة، تواصل مع الدعم.');
                }

                // ============================================================
                //  💵 5. إضافة الرصيد
                // ============================================================
                $balanceColumn = $gift->currency === 'USD' ? 'balance_usd' : 'balance_nsp';

                Wallet::where('id', $wallet->id)->update([
                    $balanceColumn => DB::raw("{$balanceColumn} + {$gift->value}"),
                ]);

                // ============================================================
                //  📝 6. تسجيل العملية في transactions
                // ============================================================
                $transaction = Transaction::create([
                    'reference'    => 'GIFT_' . strtoupper(Str::random(12)),
                    'user_id'      => $userId,
                    'to_wallet_id' => $wallet->id,
                    'type'         => 'admin_credit',
                    'to_currency'  => $gift->currency,
                    'amount_to'    => $gift->value,
                    'status'       => 'completed',
                    'completed_at' => now(),
                    'notes'        => "استبدال كود هدية: {$gift->code}",
                    'metadata'     => [
                        'source'       => 'gift_code',
                        'gift_code_id' => $gift->id,
                        'gift_code'    => $gift->code,
                    ],
                ]);

                // ============================================================
                //  📝 7. تسجيل الاستبدال
                // ============================================================
                GiftRedemption::create([
                    'gift_code_id'   => $gift->id,
                    'user_id'        => $userId,
                    'value'          => $gift->value,
                    'currency'       => $gift->currency,
                    'transaction_id' => $transaction->id,
                    'redeemed_at'    => now(),
                ]);

                return [
                    'ok'          => true,
                    'msg'         => "🎉 مبروك! تم إضافة {$gift->value} {$gift->currency} إلى محفظتك.",
                    'value'       => (float) $gift->value,
                    'currency'    => $gift->currency,
                    'gift'        => $gift->fresh(),
                    'user'        => $user,
                    'transaction' => $transaction,
                ];
            });

            // ============================================================
            //  📢 8. إشعار قناة العمليات (بعد نجاح الـ transaction)
            // ============================================================
            if (($result['ok'] ?? false)
                && isset($result['user'], $result['gift'], $result['transaction'])
            ) {
                $this->notifyTransactionsChannel(
                    $result['user'],
                    $result['gift'],
                    $result['transaction'],
                );
            }

            return $result;
        } catch (RuntimeException $e) {
            return ['ok' => false, 'msg' => $e->getMessage()];
        } catch (\Throwable $e) {
            Log::error('GiftCodeService::redeem failed', [
                'user_id' => $userId,
                'code'    => $codeText,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return ['ok' => false, 'msg' => '❌ حدث خطأ غير متوقع. حاول لاحقاً.'];
        }
    }

    // ============================================================
    //  📢 إشعار قناة العمليات عند استبدال كود هدية
    // ============================================================
    protected function notifyTransactionsChannel(User $user, GiftCode $gift, Transaction $transaction): void
    {
        $channelId = config('services.telegram.transactions_channel_id');

        if (! $channelId) {
            Log::warning('Gift code notification skipped: transactions_channel_id not configured', [
                'gift_id' => $gift->id,
            ]);
            return;
        }

        try {
            /** @var \SergiX44\Nutgram\Nutgram $bot */
            $bot = app(\SergiX44\Nutgram\Nutgram::class);

            // ============================================================
            //  👤 اسم العرض (بالأولوية: first_name → telegram_username → username)
            // ============================================================
            if (! empty($user->first_name)) {
                $displayName = trim($user->first_name . ' ' . ($user->last_name ?? ''));
            } elseif (! empty($user->telegram_username)) {
                $displayName = $user->telegram_username;
            } elseif (! empty($user->username)) {
                $displayName = $user->username;
            } else {
                $displayName = 'مستخدم #' . $user->id;
            }

            // 🔗 اسم تيليجرام
            $telegramUsername = $user->telegram_username
                ? '@' . $user->telegram_username
                : '—';

            // 🎮 اسم المنصة
            $platformUsername = $user->username ?? '—';

            // ============================================================
            //  📊 حالة الكود
            // ============================================================
            $remaining = max(0, $gift->max_uses - $gift->used_count);
            $now = now()->format('Y-m-d H:i:s');

            // ============================================================
            //  📝 بناء الرسالة (بدون زر)
            // ============================================================
            $text = implode("\n", [
                '🎁 <b>عملية هدية جديدة</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '📌 <b>النوع:</b> استبدال كود هدية',
                '',
                '👤 <b>المستخدم:</b>',
                '├─ 🆔 <code>' . $user->id . '</code>',
                '├─ 📛 <b>' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '</b>',
                '├─ 🔗 تيليجرام: <code>' . htmlspecialchars($telegramUsername, ENT_QUOTES, 'UTF-8') . '</code>',
                '└─ 🎮 المنصة: <code>' . htmlspecialchars($platformUsername, ENT_QUOTES, 'UTF-8') . '</code>',
                '',
                '🎫 <b>الكود:</b> <code>' . $gift->code . '</code>',
                '💰 <b>القيمة:</b> <b>' . number_format($gift->value, 2) . ' ' . $gift->currency . '</b>',
                '',
                '📊 <b>حالة الكود:</b>',
                '├─ 👥 استُخدم: <b>' . $gift->used_count . ' / ' . $gift->max_uses . '</b>',
                '└─ ' . ($remaining === 0
                    ? '✅ <b>استُنفد الكود بالكامل</b>'
                    : '⏳ <b>متبقٍ ' . $remaining . ' مستخدم</b>'),
                '',
                '🧾 <b>مرجع العملية:</b>',
                '└─ <code>' . $transaction->reference . '</code>',
                '',
                '⏰ <b>الوقت:</b> ' . $now,
            ]);

            // ✅ بدون زر — فقط رسالة نصية
            $bot->sendMessage(
                text: $text,
                chat_id: (int) $channelId,
                parse_mode: 'HTML',
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send gift code notification to transactions channel', [
                'gift_id'        => $gift->id,
                'user_id'        => $user->id,
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * حفظ رسالة القناة (للنشر والتعديل لاحقاً)
     */
    public function attachChannelMessage(GiftCode $gift, int $channelId, int $messageId): void
    {
        $gift->update([
            'channel_id'     => $channelId,
            'channel_msg_id' => $messageId,
        ]);
    }

    /**
     * تعطيل كود
     */
    public function disable(int $codeId): bool
    {
        $gift = GiftCode::find($codeId);
        if (! $gift || $gift->status !== 'active') {
            return false;
        }
        $gift->update(['status' => 'disabled']);
        return true;
    }


    /**
     * تحويل نص المدة إلى ثواني
     *
     * الصيغ المدعومة:
     *   - 30s   → 30 ثانية
     *   - 5m    → 300 ثانية
     *   - 2h    → 7200 ثانية
     *   - 1d    → 86400 ثانية
     *   - 10s, 5m, 1h, 2d  (مع مسافة أو بدون)
     *
     * @return int|null عدد الثواني، أو null إذا كانت الصيغة غير صحيحة
     */
    public static function parseDuration(string $input): ?int
    {
        $input = strtolower(trim($input));

        // إزالة المسافات
        $input = str_replace(' ', '', $input);

        // قبول صيغ عربية مختصرة
        $input = str_replace(
            ['ثانية', 'ثواني', 'ثانيتين', 'sec', 'second', 'seconds'],
            's',
            $input
        );
        $input = str_replace(
            ['دقيقة', 'دقائق', 'دقيقتين', 'min', 'minute', 'minutes'],
            'm',
            $input
        );
        $input = str_replace(
            ['ساعة', 'ساعات', 'ساعتين', 'hour', 'hours'],
            'h',
            $input
        );
        $input = str_replace(
            ['يوم', 'أيام', 'يومين', 'day', 'days'],
            'd',
            $input
        );

        // الصيغة: رقم + حرف
        if (! preg_match('/^(\d+)([smhd])$/', $input, $matches)) {
            return null;
        }

        $value = (int) $matches[1];
        $unit  = $matches[2];

        if ($value <= 0) {
            return null;
        }

        $seconds = match ($unit) {
            's' => $value,
            'm' => $value * 60,
            'h' => $value * 3600,
            'd' => $value * 86400,
            default => null,
        };

        if ($seconds === null || $seconds > 31536000) { // سنة كحد أقصى
            return null;
        }

        return $seconds;
    }
}
