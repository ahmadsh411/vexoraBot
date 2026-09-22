<?php

namespace App\Services;

use App\Models\Setting;

class WithdrawFeeService
{
    /**
     * حساب عمولة السحب.
     *
     * @return array{requested: float, fee: float, net: float, total: float, percent: float}
     */
    public function calculate(float $amount): array
    {
        $percent = $this->getPercent();

        if ($percent <= 0) {
            return [
                'requested' => round($amount, 2),
                'fee'       => 0.0,
                'net'       => round($amount, 2),
                'total'     => round($amount, 2),
                'percent'   => 0.0,
            ];
        }

        $fee = round($amount * ($percent / 100), 2);

        return [
            'requested' => round($amount, 2),
            'fee'       => $fee,
            'net'       => round($amount, 2),
            'total'     => round($amount + $fee, 2),
            'percent'   => $percent,
        ];
    }

    public function getPercent(): float
    {
        return (float) Setting::get('withdraw_fee_percent', 0);
    }

    public function getDescription(): string
    {
        return $this->getPercent() . '%';
    }

    public function isEnabled(): bool
    {
        return $this->getPercent() > 0;
    }
}
