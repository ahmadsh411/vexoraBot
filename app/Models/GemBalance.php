<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GemBalance extends Model
{
    protected $fillable = [
        'user_id',
        'balance',
        'total_earned',
        'total_spent',
    ];

    protected $casts = [
        'balance'      => 'integer',
        'total_earned' => 'integer',
        'total_spent'  => 'integer',
    ];

    // ============================================================
    //  Relations
    // ============================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============================================================
    //  Helpers
    // ============================================================

    public function credit(int $amount): void
    {
        if ($amount <= 0) return;

        $this->increment('balance', $amount);
        $this->increment('total_earned', $amount);
        $this->refresh();
    }

    public function debit(int $amount): bool
    {
        if ($amount <= 0) return false;
        if ($this->balance < $amount) return false;

        $this->decrement('balance', $amount);
        $this->increment('total_spent', $amount);
        $this->refresh();

        return true;
    }

    public function has(int $amount): bool
    {
        return $this->balance >= $amount;
    }
}
