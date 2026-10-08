<?php

namespace App\Services\Jarvis;

/**
 * IntentDetectionService
 *
 * Rule-based intent parser supporting English, Hindi, and Hinglish commands.
 * Works without any paid AI API. Gemini/OpenAI can be layered on top via AIProviderService.
 */
class IntentDetectionService
{
    public function __construct(
        private readonly WebsiteRegistryService $registry
    ) {}

    /**
     * Detect the intent from a normalized voice command string.
     *
     * @return array{intent: string, website?: string, url?: string, query?: string, new_tab: bool, engine?: string}
     */
    public function detect(string $command): array
    {
        $cmd = strtolower(trim($command));

        // Devanagari Hindi normalization for voice recognition in Hindi accent
        $hindiMap = [
            'ओपन'         => 'open',
            'ऑपन'         => 'open',
            'युटुब'       => 'youtube',
            'युट्युब'      => 'youtube',
            'युट्यूब'      => 'youtube',
            'यूट्यूब'       => 'youtube',
            'यू ट्यूब'      => 'youtube',
            'यू ट्युब'     => 'youtube',
            'यू-ट्यूब'      => 'youtube',
            'इन्स्टाग्राम'    => 'instagram',
            'इंस्टाग्राम'     => 'instagram',
            'इन्स्टा'       => 'instagram',
            'इंस्टा'        => 'instagram',
            'व्हाट्सएप'     => 'whatsapp',
            'व्हाट्सऐप'     => 'whatsapp',
            'व्हाट्स एप'    => 'whatsapp',
            'वाट्सएप'     => 'whatsapp',
            'गूगल'         => 'google',
            'गुगल'         => 'google',
            'जीमेल'        => 'gmail',
            'जिमेल'        => 'gmail',
            'गीथब'        => 'github',
            'गिटहब'        => 'github',
            'फेसबुक'       => 'facebook',
            'ट्विटर'        => 'twitter',
            'अमेज़न'        => 'amazon',
            'अमेजॉन'       => 'amazon',
            'अमेजन'        => 'amazon',
            'नेटफ्लिक्स'    => 'netflix',
            'चैटजीपीटी'     => 'chatgpt',
            'चैट जीपीटी'    => 'chatgpt',
            'जेमिनी'       => 'gemini',
            'खोलो'         => 'kholo',
            'खोलें'        => 'kholo',
            'खोलना'        => 'kholo',
            'सर्च'         => 'search',
            'ढूंढो'         => 'dhundo',
            'न्यू टैब'      => 'new tab',
            'नया टैब'      => 'new tab',
            'नये टैब'      => 'new tab',
            'टैब'          => 'tab',
            'बंद करो'      => 'band karo',
            'मदद'          => 'madad',
            'नमस्ते'       => 'namaste',
            'जार्विस'       => 'jarvis',
            'जारविस'       => 'jarvis',
        ];
        foreach ($hindiMap as $hindi => $eng) {
            $cmd = str_replace($hindi, $eng, $cmd);
        }

        // ── 1. Greeting ────────────────────────────────────────────────────────
        if ($this->matchesAny($cmd, [
            'hello jarvis', 'hey jarvis', 'hi jarvis',
            'jarvis hello', 'jarvis namaste', 'namaste jarvis',
            'good morning jarvis', 'good evening jarvis',
        ])) {
            return ['intent' => 'greet', 'new_tab' => false];
        }

        // ── 2. Help ────────────────────────────────────────────────────────────
        if ($this->matchesAny($cmd, [
            'help', 'help me', 'madad karo', 'kya kar sakte ho',
            'what can you do', 'commands', 'jarvis help',
        ])) {
            return ['intent' => 'help', 'new_tab' => false];
        }

        // ── 3. Close Tab (must run before Stop Listening to capture 'tab band karo') ──
        if ($this->matchesAny($cmd, [
            'close tab', 'close this tab', 'tab close karo',
            'tab band karo', 'current tab close karo', 'is tab ko band karo',
        ])) {
            return ['intent' => 'close_tab', 'new_tab' => false];
        }

        // ── 4. Stop Listening ──────────────────────────────────────────────────
        if ($this->matchesAny($cmd, [
            'stop listening', 'stop', 'jarvis stop', 'sunna band karo',
            'band karo', 'bas karo', 'quiet', 'shhh',
        ])) {
            return ['intent' => 'stop_listening', 'new_tab' => false];
        }

        // ── 5. Navigate Back ──────────────────────────────────────────────────
        if ($this->matchesAny($cmd, [
            'go back', 'back', 'back jao', 'peeche jao', 'wapas jao',
            'previous page', 'navigate back',
        ])) {
            return ['intent' => 'navigate_back', 'new_tab' => false];
        }

        // ── 6. Navigate Forward ───────────────────────────────────────────────
        if ($this->matchesAny($cmd, [
            'go forward', 'forward', 'aage jao', 'next page',
            'navigate forward',
        ])) {
            return ['intent' => 'navigate_forward', 'new_tab' => false];
        }

        // ── 7. Refresh Page ───────────────────────────────────────────────────
        if ($this->matchesAny($cmd, [
            'refresh', 'reload', 'refresh page', 'reload page',
            'page refresh karo', 'refresh karo', 'dobara load karo',
        ])) {
            return ['intent' => 'refresh_page', 'new_tab' => false];
        }

        // ── 8. YouTube Search ─────────────────────────────────────────────────
        $youtubeSearch = $this->extractYoutubeSearch($cmd);
        if ($youtubeSearch !== null) {
            return [
                'intent'  => 'search_youtube',
                'query'   => $youtubeSearch,
                'url'     => $this->registry->buildYoutubeSearchUrl($youtubeSearch),
                'new_tab' => $this->detectNewTab($cmd),
            ];
        }

        // ── 9. Google Search ─────────────────────────────────────────────────
        $googleSearch = $this->extractGoogleSearch($cmd);
        if ($googleSearch !== null) {
            return [
                'intent'  => 'search_web',
                'query'   => $googleSearch,
                'engine'  => 'google',
                'url'     => $this->registry->buildGoogleSearchUrl($googleSearch),
                'new_tab' => $this->detectNewTab($cmd),
            ];
        }

        // ── 10. Open Website (must run before standalone New Tab) ────────────
        $websiteResult = $this->extractWebsiteOpen($cmd);
        if ($websiteResult !== null) {
            $newTab = $this->detectNewTab($cmd);
            return [
                'intent'  => $newTab ? 'open_website_new_tab' : 'open_website',
                'website' => $websiteResult['key'],
                'url'     => $websiteResult['url'],
                'name'    => $websiteResult['name'],
                'new_tab' => $newTab,
            ];
        }

        // ── 11. Standalone New Tab ───────────────────────────────────────────
        if ($this->matchesAny($cmd, [
            'new tab', 'open new tab', 'new tab kholo', 'naya tab kholo',
            'tab kholo',
        ])) {
            return ['intent' => 'new_tab', 'new_tab' => true];
        }

        // ── 12. Unknown ───────────────────────────────────────────────────────
        return ['intent' => 'unknown', 'new_tab' => false];
    }

    /**
     * Check if command matches any phrase in the list (substring match).
     */
    private function matchesAny(string $cmd, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (str_contains($cmd, $phrase)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detect "new tab" intent from Hinglish / English phrases.
     */
    private function detectNewTab(string $cmd): bool
    {
        $newTabPhrases = [
            'new tab', 'new tab mein', 'naye tab mein',
            'naye tab me', 'new tab me', 'new tab par',
            'alag tab', 'different tab',
        ];
        return $this->matchesAny($cmd, $newTabPhrases);
    }

    /**
     * Try to extract a website name from an open command.
     */
    private function extractWebsiteOpen(string $cmd): ?array
    {
        // Common open verb patterns (English + Hindi + Hinglish)
        $openVerbs = [
            'open', 'kholo', 'kholna', 'launch', 'start',
            'open karo', 'chalu karo', 'chalao',
        ];

        // Strip open verbs and known noise words to find the website name
        $cleaned = $cmd;
        $noiseWords = array_merge($openVerbs, [
            'jarvis', 'please', 'mujhe', 'abhi', 'zara',
            'new tab', 'new tab mein', 'naye tab mein', 'naye tab me',
            'new tab me', 'new tab par', 'in a', 'in new tab', 'a new tab',
        ]);
        foreach ($noiseWords as $noise) {
            $cleaned = str_replace($noise, '', $cleaned);
        }
        $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));

        if (empty($cleaned)) {
            return null;
        }

        return $this->registry->resolve($cleaned);
    }

    /**
     * Extract search query for YouTube.
     * Patterns: "youtube par X search karo", "search X on youtube", etc.
     */
    private function extractYoutubeSearch(string $cmd): ?string
    {
        $patterns = [
            // "youtube par X search karo"
            '/youtube\s+par\s+(.+?)\s+(?:search\s+karo|dhundo|dhundho|search)/i',
            // "search X on youtube" / "X search karo youtube par"
            '/search\s+(.+?)\s+on\s+youtube/i',
            '/(.+?)\s+(?:search|dhundo)\s+youtube\s+(?:par|pe|on)/i',
            // "youtube mein X search karo"
            '/youtube\s+(?:mein|me|par|pe)\s+(.+?)\s+(?:search|dhundo)/i',
            '/(.+?)\s+(?:search|dhundo)\s+(?:on\s+)?youtube/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cmd, $matches)) {
                $query = trim($matches[1]);
                if (strlen($query) > 1) {
                    return $query;
                }
            }
        }
        return null;
    }

    /**
     * Extract search query for Google (or generic web search).
     */
    private function extractGoogleSearch(string $cmd): ?string
    {
        $patterns = [
            // "google par X search karo"
            '/google\s+par\s+(.+?)\s+(?:search\s+karo|dhundo|search)/i',
            // "search X on google"
            '/search\s+(.+?)\s+on\s+google/i',
            // "google karo X" / "google search X"
            '/google\s+(?:search|karo)\s+(.+)/i',
            // "X search karo google par"
            '/(.+?)\s+(?:search|dhundo)\s+(?:on\s+)?google/i',
            // "search for X"
            '/search\s+for\s+(.+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cmd, $matches)) {
                $query = trim($matches[1]);
                if (strlen($query) > 1) {
                    return $query;
                }
            }
        }
        return null;
    }
}
