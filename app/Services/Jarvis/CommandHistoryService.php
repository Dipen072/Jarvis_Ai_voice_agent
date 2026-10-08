<?php

namespace App\Services\Jarvis;

use App\Models\JarvisCommandHistory;
use Illuminate\Support\Facades\Auth;

/**
 * CommandHistoryService
 *
 * Records command executions to the database.
 * History storage can be disabled via config.
 */
class CommandHistoryService
{
    /**
     * Record a completed command execution.
     */
    public function record(
        string $rawCommand,
        string $normalizedCommand,
        array  $intentResult,
        string $response,
        string $status,
        int    $executionTimeMs,
        string $ipAddress
    ): ?JarvisCommandHistory {
        if (! config('jarvis.store_history', true)) {
            return null;
        }

        return JarvisCommandHistory::create([
            'user_id'            => Auth::id(),
            'raw_command'        => substr($rawCommand, 0, 500),
            'normalized_command' => substr($normalizedCommand, 0, 500),
            'detected_intent'    => $intentResult['intent'] ?? 'unknown',
            'parameters'         => $intentResult,
            'response_message'   => $response,
            'execution_status'   => $status,
            'execution_time_ms'  => $executionTimeMs,
            'ip_address'         => $ipAddress,
        ]);
    }

    /**
     * Get paginated history for the current user.
     */
    public function forUser(int $userId, int $perPage = 20)
    {
        return JarvisCommandHistory::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Clear all history for the current user.
     */
    public function clearForUser(int $userId): int
    {
        return JarvisCommandHistory::where('user_id', $userId)->delete();
    }

    /**
     * Get recent commands (last 10) for dashboard display.
     */
    public function recentForUser(int $userId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return JarvisCommandHistory::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
