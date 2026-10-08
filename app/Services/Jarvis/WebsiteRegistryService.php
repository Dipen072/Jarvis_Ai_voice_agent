<?php

namespace App\Services\Jarvis;

/**
 * WebsiteRegistryService
 *
 * Maintains the allowlist of permitted websites Jarvis can open.
 * All URLs are validated here. No arbitrary URLs are ever permitted.
 */
class WebsiteRegistryService
{
    /**
     * Allowlisted websites with their canonical URL and keyword aliases.
     * Aliases are matched case-insensitively.
     */
    private array $registry = [
        'instagram' => [
            'url'     => 'https://www.instagram.com/',
            'name'    => 'Instagram',
            'aliases' => ['instagram', 'insta', 'ig', 'इंस्टाग्राम', 'इन्स्टाग्राम', 'इन्स्टा', 'इंस्टा'],
        ],
        'youtube' => [
            'url'     => 'https://www.youtube.com/',
            'name'    => 'YouTube',
            'aliases' => ['youtube', 'yt', 'you tube', 'युटुब', 'यूट्यूब', 'युट्युब', 'युट्यूब', 'यू ट्यूब', 'यू ट्युब'],
        ],
        'whatsapp' => [
            'url'     => 'https://web.whatsapp.com/',
            'name'    => 'WhatsApp',
            'aliases' => ['whatsapp', 'whats app', 'wa', 'व्हाट्सएप', 'व्हाट्सऐप', 'वाट्सएप'],
        ],
        'chatgpt' => [
            'url'     => 'https://chatgpt.com/',
            'name'    => 'ChatGPT',
            'aliases' => ['chatgpt', 'chat gpt', 'openai', 'gpt', 'चैटजीपीटी', 'चैट जीपीटी'],
        ],
        'google' => [
            'url'     => 'https://www.google.com/',
            'name'    => 'Google',
            'aliases' => ['google', 'गूगल', 'गुगल'],
        ],
        'gemini' => [
            'url'     => 'https://gemini.google.com/',
            'name'    => 'Gemini',
            'aliases' => ['gemini', 'bard', 'google gemini', 'जेमिनी', 'गेमिनी'],
        ],
        'github' => [
            'url'     => 'https://github.com/',
            'name'    => 'GitHub',
            'aliases' => ['github', 'git hub', 'गिटहब', 'गीथब'],
        ],
        'gmail' => [
            'url'     => 'https://mail.google.com/',
            'name'    => 'Gmail',
            'aliases' => ['gmail', 'google mail', 'जीमेल', 'जिमेल'],
        ],
        'facebook' => [
            'url'     => 'https://www.facebook.com/',
            'name'    => 'Facebook',
            'aliases' => ['facebook', 'fb', 'face book', 'फेसबुक'],
        ],
        'twitter' => [
            'url'     => 'https://twitter.com/',
            'name'    => 'X (Twitter)',
            'aliases' => ['twitter', 'x', 'tweet', 'ट्विटर'],
        ],
        'netflix' => [
            'url'     => 'https://www.netflix.com/',
            'name'    => 'Netflix',
            'aliases' => ['netflix', 'नेटफ्लिक्स'],
        ],
        'amazon' => [
            'url'     => 'https://www.amazon.in/',
            'name'    => 'Amazon',
            'aliases' => ['amazon', 'amazon india', 'अमेज़न', 'अमेजॉन', 'अमेजन'],
        ],
        'linkedin' => [
            'url'     => 'https://www.linkedin.com/',
            'name'    => 'LinkedIn',
            'aliases' => ['linkedin', 'linked in', 'लिंक्डइन'],
        ],
        'stackoverflow' => [
            'url'     => 'https://stackoverflow.com/',
            'name'    => 'Stack Overflow',
            'aliases' => ['stackoverflow', 'stack overflow'],
        ],
        'maps' => [
            'url'     => 'https://maps.google.com/',
            'name'    => 'Google Maps',
            'aliases' => ['maps', 'google maps', 'map', 'गूगल मैप्स'],
        ],
    ];

    /**
     * Resolve a spoken website name/alias to its registry entry.
     */
    public function resolve(string $keyword): ?array
    {
        $keyword = strtolower(trim($keyword));

        foreach ($this->registry as $key => $site) {
            foreach ($site['aliases'] as $alias) {
                if ($keyword === $alias || preg_match('/\b' . preg_quote($alias, '/') . '\b/i', $keyword)) {
                    return [
                        'key'  => $key,
                        'url'  => $site['url'],
                        'name' => $site['name'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Validate that a given URL is in the allowlist.
     * Used to re-validate frontend-submitted URLs server-side.
     */
    public function isUrlAllowed(string $url): bool
    {
        $url = strtolower(trim($url));

        // Reject non-http(s) schemes entirely
        if (!str_starts_with($url, 'https://') && !str_starts_with($url, 'http://')) {
            return false;
        }

        foreach ($this->registry as $site) {
            if (str_starts_with($url, strtolower($site['url']))) {
                return true;
            }
        }

        // Also allow Google search URLs
        if (str_starts_with($url, 'https://www.google.com/search')) {
            return true;
        }
        if (str_starts_with($url, 'https://www.youtube.com/results')) {
            return true;
        }

        return false;
    }

    /**
     * Return the full registry (for frontend quick-launch buttons).
     */
    public function all(): array
    {
        return $this->registry;
    }

    /**
     * Build a Google search URL from a query string.
     */
    public function buildGoogleSearchUrl(string $query): string
    {
        return 'https://www.google.com/search?q=' . urlencode($query);
    }

    /**
     * Build a YouTube search URL from a query string.
     */
    public function buildYoutubeSearchUrl(string $query): string
    {
        return 'https://www.youtube.com/results?search_query=' . urlencode($query);
    }
}
