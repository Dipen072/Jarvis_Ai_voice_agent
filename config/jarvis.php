<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Jarvis AI Configuration
    |--------------------------------------------------------------------------
    */

    // Jarvis display name
    'voice_name' => env('JARVIS_VOICE_NAME', 'Jarvis'),

    // Whether to store command history in the database
    'store_history' => (bool) env('JARVIS_STORE_HISTORY', true),

    // Maximum history records per user (older ones are pruned)
    'max_history' => (int) env('JARVIS_MAX_HISTORY', 100),

    // Rate limit: commands per minute
    'rate_limit' => (int) env('JARVIS_RATE_LIMIT', 60),

    // AI Provider: 'none' | 'gemini' | 'openai'
    'ai_provider' => env('AI_PROVIDER', 'none'),

    // Gemini API key (optional)
    'gemini_api_key' => env('GEMINI_API_KEY'),

    // OpenAI API key (optional)
    'openai_api_key' => env('OPENAI_API_KEY'),

    // OpenAI model
    'openai_model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
];
