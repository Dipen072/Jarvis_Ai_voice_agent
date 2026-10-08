<?php

namespace App\Services\Jarvis;

use Illuminate\Support\Str;

/**
 * VoiceCommandService
 *
 * Orchestrates the full command processing pipeline:
 *   raw text → normalize → detect intent → generate response → build action
 */
class VoiceCommandService
{
    public function __construct(
        private readonly AIProviderService     $aiProvider,
        private readonly JarvisResponseService $responseService,
        private readonly WebsiteRegistryService $registry,
    ) {}

    /**
     * Process a raw voice command string and return a structured response.
     *
     * @return array{success: bool, intent: string, message: string, action: array}
     */
    public function process(string $rawCommand): array
    {
        $normalized = $this->normalize($rawCommand);

        // Detect intent (AI or rule-based)
        $intentResult = $this->aiProvider->process($normalized);

        // Build action payload for frontend
        $action = $this->buildAction($intentResult);

        // Generate spoken response
        $message = $this->responseService->generate(
            $intentResult['intent'],
            $intentResult
        );

        return [
            'success'  => true,
            'intent'   => $intentResult['intent'],
            'params'   => $intentResult,
            'message'  => $message,
            'action'   => $action,
            'source'   => $intentResult['source'] ?? 'rule_based',
        ];
    }

    /**
     * Normalize a raw command string:
     * - Lowercase
     * - Strip punctuation
     * - Trim whitespace
     * - Collapse multiple spaces
     */
    public function normalize(string $command): string
    {
        // Lowercase
        $cmd = strtolower($command);

        // Remove leading "jarvis" trigger word (keep the actual command)
        $stripped = preg_replace('/^\s*(?:jarvis|hey jarvis|hello jarvis|hi jarvis)\s*,?\s*/i', '', $cmd);
        $cmd = trim((string) $stripped) === '' ? 'hello jarvis' : (string) $stripped;

        // Remove punctuation except for spaces
        $cmd = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $cmd);

        // Collapse spaces
        $cmd = preg_replace('/\s+/', ' ', $cmd);

        return trim($cmd);
    }

    /**
     * Build the action payload the frontend executes.
     * The frontend validates this against its own allowlist before execution.
     */
    private function buildAction(array $intent): array
    {
        return match ($intent['intent']) {
            'open_website', 'open_website_new_tab' => [
                'type'    => 'open_url',
                'url'     => $intent['url'] ?? '',
                'new_tab' => $intent['new_tab'] ?? false,
            ],
            'search_web', 'search_youtube' => [
                'type'    => 'open_url',
                'url'     => $intent['url'] ?? '',
                'new_tab' => $intent['new_tab'] ?? false,
                'query'   => $intent['query'] ?? '',
            ],
            'navigate_back'    => ['type' => 'navigate', 'direction' => 'back'],
            'navigate_forward' => ['type' => 'navigate', 'direction' => 'forward'],
            'refresh_page'     => ['type' => 'refresh'],
            'close_tab'        => ['type' => 'close_tab'],
            'new_tab'          => ['type' => 'new_tab'],
            'stop_listening'   => ['type' => 'stop_listening'],
            'greet', 'help', 'unknown' => ['type' => 'speak_only'],
            default                    => ['type' => 'speak_only'],
        };
    }
}
