<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wheel;
use App\Services\WheelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WheelApiController extends Controller
{
    /**
     * التحقق من Telegram InitData.
     */
    private function validateTelegram(Request $request): ?User
    {
        $initData = $request->header('X-Telegram-Init-Data');
        $telegramId = $request->input('telegram_id');

        if (! $telegramId) {
            return null;
        }

        return User::where('telegram_id', $telegramId)->first();
    }

    /**
     * حالة العجلة.
     */
    public function state(Request $request)
    {
        $user = $this->validateTelegram($request);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير موجود',
            ], 401);
        }

        $wheelService = app(WheelService::class);
        $state = $wheelService->getState($user);

        if (! $state) {
            return response()->json([
                'success' => false,
                'message' => 'العجلة غير مفعّلة',
            ], 400);
        }

        // ✅ لا نرسل weight (النسب) — فقط البيانات الأساسية
        $prizes = $state['wheel']->activePrizes()
            ->orderBy('sort_order')
            ->get()
            ->map(fn($p) => [
                'id'         => $p->id,
                'name'       => $p->name,
                'icon'       => $p->icon,
                'value'      => (float) $p->value,
                'currency'   => $p->currency,
                'type'       => $p->type,
                'is_recycle' => $p->isRecycle(),
                'is_empty'   => $p->isEmpty(),
            ]);

        return response()->json([
            'success'          => true,
            'prizes'           => $prizes,
            'available_spins'  => $state['available_spins'],
            'spins_today'      => $state['spins_today'],
            'daily_limit'      => $state['daily_limit'],
            'total_won_amount' => $state['total_won_amount'],
        ]);
    }

    /**
     * لف العجلة.
     */
    public function spin(Request $request)
    {
        $user = $this->validateTelegram($request);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير موجود',
            ], 401);
        }

        try {
            $wheelService = app(WheelService::class);
            $result = $wheelService->spin($user);

            $prize = $result['prize'];
            $spin  = $result['spin'];
            $state = $result['state'];

            // ✅ نرسل prize_id فقط — لا prize_index
            Log::info('Wheel API spin', [
                'user_id'   => $user->id,
                'prize_id'  => $prize->id,
                'won_value' => $spin->won_value,
            ]);

            return response()->json([
                'success'          => true,
                'prize_id'         => $prize->id,
                'prize'            => [
                    'id'         => $prize->id,
                    'name'       => $prize->name,
                    'icon'       => $prize->icon,
                    'is_recycle' => $prize->isRecycle(),
                    'is_empty'   => $prize->isEmpty(),
                ],
                'won_value'        => (float) $spin->won_value,
                'currency'         => $spin->currency,
                'available_spins'  => (int) $state->available_spins,
                'spins_today'      => (int) $state->spins_today,
                'total_won_amount' => (float) $state->total_won_amount,
            ]);
        } catch (\Throwable $e) {
            Log::error('Wheel API spin failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
