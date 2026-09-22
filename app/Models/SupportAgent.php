<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportAgent extends Model
{
    use HasFactory;

    protected $table = 'support_agents';

    protected $fillable = [
        'name',
        'username',
        'telegram_id',
        'channels_joined_at',
        'join_message_id',
        'icon',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'telegram_id'        => 'integer',
        'channels_joined_at' => 'datetime',
        'join_message_id'    => 'integer',
        'sort_order'         => 'integer',
        'is_active'          => 'boolean',
    ];

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    // ============================================================
    //  Helpers
    // ============================================================

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function hasTelegramId(): bool
    {
        return ! is_null($this->telegram_id);
    }

    public function hasJoinedChannels(): bool
    {
        return ! is_null($this->channels_joined_at);
    }

    // ============================================================
    //  Accessors
    // ============================================================

    public function getFullNameAttribute(): string
    {
        return $this->icon . ' ' . $this->name;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? '🟢 متاح' : '🔴 غير متاح';
    }

    public function getTelegramUrlAttribute(): string
    {
        $username = ltrim($this->username, '@');

        return "https://t.me/{$username}";
    }

    public function getCleanUsernameAttribute(): string
    {
        return ltrim($this->username ?? '', '@');
    }
}
