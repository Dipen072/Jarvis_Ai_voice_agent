<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JarvisCommandHistory extends Model
{
    protected $fillable = [
        'user_id',
        'raw_command',
        'normalized_command',
        'detected_intent',
        'parameters',
        'response_message',
        'execution_status',
        'execution_time_ms',
        'ip_address',
    ];

    protected $casts = [
        'parameters' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Scope: only records for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: only successful executions.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('execution_status', 'success');
    }
}
