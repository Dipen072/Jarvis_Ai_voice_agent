<?php

namespace App\Services\Jarvis;

/**
 * JarvisResponseService
 *
 * Generates natural spoken responses for each intent.
 * Supports English and Hinglish response styles.
 */
class JarvisResponseService
{
    private array $greetings = [
        "Hello sir, how may I assist you today?",
        "At your service, sir. What can I do for you?",
        "Good day, sir. How can I help?",
        "Jarvis online. What do you need, sir?",
        "Hello! I'm listening, sir.",
    ];

    /**
     * Generate a response message for a detected intent and its parameters.
     */
    public function generate(string $intent, array $params = []): string
    {
        return match ($intent) {
            'greet'                 => $this->randomFrom($this->greetings),
            'open_website'          => $this->openWebsiteResponse($params, false),
            'open_website_new_tab'  => $this->openWebsiteResponse($params, true),
            'search_web'            => $this->searchResponse($params, 'Google'),
            'search_youtube'        => $this->searchResponse($params, 'YouTube'),
            'navigate_back'         => "Going back, sir.",
            'navigate_forward'      => "Moving forward, sir.",
            'refresh_page'          => "Refreshing the page, sir.",
            'close_tab'             => "Closing this tab, sir.",
            'new_tab'               => "Opening a new tab, sir.",
            'stop_listening'        => "Understood, sir. I'll stop listening. Say 'Hello Jarvis' to wake me again.",
            'help'                  => $this->helpResponse(),
            'unknown'               => $this->unknownResponse(),
            'error'                 => $params['message'] ?? "I encountered an error, sir. Please try again.",
            default                 => "Done, sir.",
        };
    }

    private function openWebsiteResponse(array $params, bool $newTab): string
    {
        $name    = $params['name'] ?? $params['website'] ?? 'the website';
        $tabText = $newTab ? ' in a new tab' : '';
        $phrases = [
            "Certainly, sir. Opening {$name}{$tabText}.",
            "Right away, sir. Loading {$name}{$tabText}.",
            "Of course, sir. {$name}{$tabText} coming right up.",
        ];
        return $this->randomFrom($phrases);
    }

    private function searchResponse(array $params, string $engine): string
    {
        $query = $params['query'] ?? 'your query';
        $phrases = [
            "Searching {$engine} for '{$query}', sir.",
            "Right away, sir. Looking up '{$query}' on {$engine}.",
            "On it, sir. Searching for '{$query}' on {$engine}.",
        ];
        return $this->randomFrom($phrases);
    }

    private function helpResponse(): string
    {
        return "Here's what I can do, sir: Open websites like Instagram, YouTube, WhatsApp, ChatGPT, Google, and Gemini. "
            . "Search on Google or YouTube. Navigate back, forward, or refresh the page. "
            . "Say 'Stop listening' to put me to sleep. "
            . "You can speak in English, Hindi, or Hinglish!";
    }

    private function unknownResponse(): string
    {
        $responses = [
            "I'm sorry, sir. I didn't quite understand that. Could you rephrase?",
            "I didn't catch that, sir. Could you say that again?",
            "Pardon, sir? I couldn't process that command. Try saying 'help' for a list of commands.",
        ];
        return $this->randomFrom($responses);
    }

    private function randomFrom(array $arr): string
    {
        return $arr[array_rand($arr)];
    }
}
