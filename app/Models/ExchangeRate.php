<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'buy_rate',
        'sell_rate',
        'commission_percent',
        'is_active',
        'updated_by',
        'notes',
    ];

    protected $casts = [
        'rate'               => 'decimal:6',
        'buy_rate'           => 'decimal:6',
        'sell_rate'          => 'decimal:6',
        'commission_percent' => 'decimal:2',
        'is_active'          => 'boolean',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBetween($query, string $from, string $to)
    {
        return $query->where('from_currency', strtoupper($from))
            ->where('to_currency', strtoupper($to));
    }

    // ============================================================
    //  Helpers
    // ============================================================

    public function convert(float $amount): float
    {
        return round($amount * (float) $this->rate, 2);
    }

    public function convertWithCommission(float $amount): float
    {
        $converted = $this->convert($amount);

        if ($this->commission_percent <= 0) {
            return $converted;
        }

        $commission = $converted * ((float) $this->commission_percent / 100);

        return round($converted - $commission, 2);
    }

    public function commissionAmount(float $amount): float
    {
        if ($this->commission_percent <= 0) {
            return 0.0;
        }

        $converted = $this->convert($amount);

        return round($converted * ((float) $this->commission_percent / 100), 2);
    }

    public function inverseRate(): float
    {
        $rate = (float) $this->rate;
        return $rate === 0.0 ? 0.0 : round(1 / $rate, 6);
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getPairLabelAttribute(): string
    {
        return "{$this->from_currency} → {$this->to_currency}";
    }
}
