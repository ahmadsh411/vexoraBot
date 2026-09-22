<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IChancyAccount extends Model
{
    protected $table = 'ichancy_accounts';

    protected $fillable = [
        'user_id',
        'ichancy_player_id',
        'ichancy_username',
        'ichancy_password_encrypted',
        'currency',
        'balance_cache',
        'last_synced_at',
        'is_active',
    ];

    protected $casts = [
        'balance_cache'  => 'decimal:2',
        'last_synced_at' => 'datetime',
        'is_active'      => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPlainPasswordAttribute(): ?string
    {
        try {
            return decrypt($this->ichancy_password_encrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
