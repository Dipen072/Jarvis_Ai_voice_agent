<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VoiceCommandRequest;
use App\Services\Jarvis\CommandHistoryService;
use App\Services\Jarvis\VoiceCommandService;
use App\Services\Jarvis\WebsiteRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VoiceCommandController extends Controller
{
    public function __construct(
        private readonly VoiceCommandService    $commandService,
        private readonly CommandHistoryService  $historyService,
        private readonly WebsiteRegistryService $registry,
    ) {}

    /**
     * POST /api/jarvis/command
     *
     * Process a voice or text command and return a structured JSON response.
     */
    public function command(VoiceCommandRequest $request): JsonResponse
    {
        $startTime   = microtime(true);
        $rawCommand  = $request->validated('command');

        try {
            $result = $this->commandService->process($rawCommand);

            // Additional server-side URL allowlist check
            if (
                isset($result['action']['url']) &&
                !empty($result['action']['url']) &&
                !$this->registry->isUrlAllowed($result['action']['url'])
            ) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Requested URL is not in the allowed list.',
                ], 403);
            }

            $executionMs = (int) ((microtime(true) - $startTime) * 1000);

            // Record to history if auth user
            if (Auth::check()) {
                $this->historyService->record(
                    rawCommand:        $rawCommand,
                    normalizedCommand: $this->commandService->normalize($rawCommand),
                    intentResult:      $result['params'],
                    response:          $result['message'],
                    status:            'success',
                    executionTimeMs:   $executionMs,
                    ipAddress:         $request->ip(),
                );
            }

            return response()->json([
                'success'  => true,
                'intent'   => $result['intent'],
                'message'  => $result['message'],
                'action'   => $result['action'],
                'source'   => $result['source'],
                'time_ms'  => $executionMs,
            ]);

        } catch (\Throwable $e) {
            $executionMs = (int) ((microtime(true) - $startTime) * 1000);
            \Illuminate\Support\Facades\Log::error("Jarvis command error: {$e->getMessage()}");

            if (Auth::check()) {
                $this->historyService->record(
                    rawCommand:        $rawCommand,
                    normalizedCommand: $this->commandService->normalize($rawCommand),
                    intentResult:      ['intent' => 'error'],
                    response:          'System error occurred.',
                    status:            'failed',
                    executionTimeMs:   $executionMs,
                    ipAddress:         $request->ip(),
                );
            }

            return response()->json([
                'success' => false,
                'intent'  => 'error',
                'message' => 'I encountered an error, sir. Please try again.',
                'action'  => ['type' => 'speak_only'],
            ], 500);
        }
    }

    /**
     * GET /api/jarvis/health
     *
     * Simple health check — no auth required.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status'      => 'online',
            'name'        => 'Jarvis AI',
            'version'     => '1.0.0',
            'timestamp'   => now()->toISOString(),
            'ai_provider' => config('jarvis.ai_provider', 'none'),
        ]);
    }

    /**
     * GET /api/jarvis/history
     *
     * Return paginated command history for the authenticated user.
     */
    public function history(Request $request): JsonResponse
    {
        $history = $this->historyService->forUser(Auth::id(), 20);

        return response()->json([
            'success' => true,
            'data'    => $history->items(),
            'meta'    => [
                'current_page' => $history->currentPage(),
                'last_page'    => $history->lastPage(),
                'total'        => $history->total(),
            ],
        ]);
    }

    /**
     * DELETE /api/jarvis/history
     *
     * Clear all command history for the authenticated user.
     */
    public function clearHistory(): JsonResponse
    {
        $deleted = $this->historyService->clearForUser(Auth::id());

        return response()->json([
            'success' => true,
            'message' => "Deleted {$deleted} history records.",
        ]);
    }

    /**
     * GET /api/jarvis/settings
     */
    public function settings(): JsonResponse
    {
        $settings = \App\Models\JarvisSetting::getAllForUser(Auth::id());

        return response()->json([
            'success'  => true,
            'settings' => $settings,
        ]);
    }

    /**
     * PUT /api/jarvis/settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $allowed = ['voice_speed', 'voice_pitch', 'voice_name', 'continuous_mode', 'theme'];

        $validated = $request->validate([
            'key'   => ['required', 'string', 'in:' . implode(',', $allowed)],
            'value' => ['required', 'string', 'max:255'],
        ]);

        \App\Models\JarvisSetting::setValue(Auth::id(), $validated['key'], $validated['value']);

        return response()->json(['success' => true, 'message' => 'Setting updated.']);
    }
}
