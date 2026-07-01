<?php

return [
    'enabled' => env('AI_COMPARISON_ENABLED', false),
    'provider' => env('AI_PROVIDER', 'openai_compatible'),
    'api_key' => env('AI_API_KEY', ''),
    'base_url' => rtrim(env('AI_BASE_URL', 'https://api.openai.com/v1'), '/'),
    'model' => env('AI_MODEL', 'gpt-4o-mini'),
    'timeout' => 120,
    'max_retries' => 2,
];
