<?php

// app/Models/GiftRedemption.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftRedemption extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'gift_code_id',
        'user_id',
        'value',
        'currency',
        'transaction_id',
        'redeemed_at',
    ];

    protected $casts = [
        'value'        => 'decimal:2',
        'redeemed_at'  => 'datetime',
    ];

    public function giftCode(): BelongsTo
    {
        return $this->belongsTo(GiftCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
