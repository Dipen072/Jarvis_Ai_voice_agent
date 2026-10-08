<?php

namespace App\Services\Jarvis;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AIProviderService
 *
 * Optional AI enhancement layer. Falls back gracefully to rule-based parser
 * when no API key is configured or if the API call fails.
 *
 * Supported providers: 'gemini', 'openai', 'none' (rule-based only).
 */
class AIProviderService
{
    public function __construct(
        private readonly IntentDetectionService $intentDetection
    ) {}

    /**
     * Process a command. If AI provider is configured and available,
     * use it to enhance understanding. Otherwise use rule-based parser.
     *
     * @return array The normalized intent array.
     */
    public function process(string $command): array
    {
        $provider = strtolower(config('jarvis.ai_provider', 'none'));
        $geminiKey = config('jarvis.gemini_api_key');
        $openaiKey = config('jarvis.openai_api_key');

        // Auto-detect provider if set to 'none' but key is present
        if ($provider === 'none') {
            if (!empty($geminiKey)) {
                $provider = 'gemini';
            } elseif (!empty($openaiKey)) {
                $provider = 'openai';
            }
        }

        // Always start with rule-based detection as a base
        $ruleResult = $this->intentDetection->detect($command);

        // If rule-based found a clear intent, trust it
        if ($ruleResult['intent'] !== 'unknown') {
            return array_merge($ruleResult, ['source' => 'rule_based']);
        }

        // Unknown intent — try AI if configured
        if ($provider === 'gemini' && !empty($geminiKey)) {
            try {
                $aiResult = $this->callGemini($command);
                if ($aiResult && $aiResult['intent'] !== 'unknown') {
                    return array_merge($aiResult, ['source' => 'gemini']);
                }
            } catch (\Throwable $e) {
                Log::warning("Gemini API failed: {$e->getMessage()}. Falling back to rule-based.");
            }
        }

        if ($provider === 'openai' && !empty($openaiKey)) {
            try {
                $aiResult = $this->callOpenAI($command);
                if ($aiResult && $aiResult['intent'] !== 'unknown') {
                    return array_merge($aiResult, ['source' => 'openai']);
                }
            } catch (\Throwable $e) {
                Log::warning("OpenAI API failed: {$e->getMessage()}. Falling back to rule-based.");
            }
        }

        return array_merge($ruleResult, ['source' => 'rule_based']);
    }

    /**
     * Call Gemini API to interpret an unknown command.
     */
    private function callGemini(string $command): ?array
    {
        $apiKey = config('jarvis.gemini_api_key');
        $systemPrompt = $this->buildSystemPrompt();

        $models = ['gemini-1.5-flash', 'gemini-2.0-flash', 'gemini-pro'];

        foreach ($models as $model) {
            $response = Http::timeout(10)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                [
                    'contents' => [
                        ['parts' => [['text' => $systemPrompt . "\n\nUser command: \"{$command}\""]]]
                    ],
                ]
            );

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text', '');
                $parsed = $this->parseAIResponse($text);
                if ($parsed) return $parsed;
            }
        }

        return null;
    }

    /**
     * Call OpenAI API to interpret an unknown command.
     */
    private function callOpenAI(string $command): ?array
    {
        $apiKey = config('jarvis.openai_api_key');
        $model  = config('jarvis.openai_model', 'gpt-4o-mini');

        $response = Http::timeout(10)
            ->withToken($apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'       => $model,
                'temperature' => 0,
                'messages'    => [
                    ['role' => 'system', 'content' => $this->buildSystemPrompt()],
                    ['role' => 'user',   'content' => "User command: \"{$command}\""],
                ],
            ]);

        if ($response->successful()) {
            $text = $response->json('choices.0.message.content', '');
            return $this->parseAIResponse($text);
        }

        return null;
    }

    /**
     * Parse AI JSON response into a normalized intent array.
     */
    private function parseAIResponse(string $text): ?array
    {
        // Extract JSON from AI response
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded) && isset($decoded['intent'])) {
                // Validate intent is one of our allowed values
                $allowedIntents = [
                    'open_website', 'open_website_new_tab', 'search_web', 'search_youtube',
                    'navigate_back', 'navigate_forward', 'refresh_page', 'close_tab',
                    'new_tab', 'greet', 'help', 'stop_listening', 'unknown',
                ];
                if (in_array($decoded['intent'], $allowedIntents)) {
                    return array_merge(['new_tab' => false], $decoded);
                }
            }
        }
        return null;
    }

    private function buildSystemPrompt(): string
    {
        return <<<PROMPT
You are a voice command parser for JARVIS AI assistant. Parse the user's command (which may be in English, Hindi, or Hinglish) and return ONLY a JSON object with these fields:
- intent: one of [open_website, open_website_new_tab, search_web, search_youtube, navigate_back, navigate_forward, refresh_page, close_tab, new_tab, greet, help, stop_listening, unknown]
- website: website key if applicable (instagram, youtube, whatsapp, chatgpt, google, gemini, github, gmail, facebook, twitter, netflix, amazon, linkedin)
- url: full URL if applicable
- query: search query if applicable
- new_tab: boolean

Respond with ONLY the JSON object, no explanation.
PROMPT;
    }
}
