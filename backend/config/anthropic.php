<?php

return [
    // Secret côté Laravel uniquement (règle 2 de CLAUDE.md).
    'api_key' => env('ANTHROPIC_API_KEY'),

    // Modèle imposé pour l'analyse séquence (CLAUDE.md : claude-sonnet-4-6).
    'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),

    'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 4000),

    'timeout_seconds' => (int) env('ANTHROPIC_TIMEOUT_SECONDS', 120),

    // Base et version de l'API Messages d'Anthropic.
    'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
    'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
];
