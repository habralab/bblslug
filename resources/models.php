<?php

/**
 * Text translation model registry
 * Defines available models, endpoints, limits, and request requirements.
 */

return [

    'deepl:free' => [
        'vendor' => 'deepl',
        'name' => 'DeepL API Free',
        'endpoint' => 'https://api-free.deepl.com/v2/translate',
        'format' => 'text|html',
        'limits' => [
            'estimated_max_chars' => 30000
        ],
        'requirements' => [
            'auth' => [
                'help_url' => 'https://www.deepl.com/account/summary',
                'type' => 'form',
                'key_name' => 'auth_key',
                'prefix' => null,
                'env' => 'DEEPL_FREE_API_KEY',
                'arg' => '--api-key-deepl'
            ],
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
            'body_type' => 'form',
            'params' => ['text', 'target_lang']
        ],
        'notes' => 'Best for structured HTML or plain text, with a limited monthly quota (free tier).',
        'defaults' => [
            'target_lang' => 'EN',
            'formality'   => 'prefer_more',
        ]
    ],

    'deepl:pro' => [
        'vendor' => 'deepl',
        'name' => 'DeepL API Pro',
        'endpoint' => 'https://api.deepl.com/v2/translate',
        'format' => 'text|html',
        'limits' => [
            'estimated_max_chars' => 30000
        ],
        'requirements' => [
            'auth' => [
                'help_url' => 'https://www.deepl.com/account/summary',
                'type' => 'form',
                'key_name' => 'auth_key',
                'prefix' => null,
                'env' => 'DEEPL_PRO_API_KEY',
                'arg' => '--api-key-deepl'
            ],
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
            'body_type' => 'form',
            'params' => ['text', 'target_lang']
        ],
        'notes' => 'Reliable structured translation for production use, requires paid subscription.',
        'defaults' => [
            'target_lang' => 'EN',
            'formality'   => 'prefer_more',
        ]
    ],

    'openai:gpt-4o' => [
        'vendor' => 'openai',
        'name' => 'OpenAI GPT-4o',
        'endpoint' => 'https://api.openai.com/v1/chat/completions',
        'limits' => [
            'max_tokens' => 128000,
            'token_estimator' => 'gpt',
            'estimated_max_chars' => 512000
        ],
        'requirements' => [
            'auth' => [
                'help_url' => 'https://platform.openai.com/account/api-keys',
                'type' => 'header',
                'key_name' => 'Authorization',
                'prefix' => 'Bearer',
                'env' => 'OPENAI_API_KEY',
                'arg' => '--api-key-openai'
            ],
            'headers' => ['Content-Type: application/json'],
        ],
        'notes' => 'Highly accurate with flexible prompts, ideal for AI-assisted adaptive translation.',
        'defaults' => [
            'model'       => 'gpt-4o',
            'temperature' => 0.0,
        ]
    ],

    'gemini:1.5-pro' => [
        'vendor' => 'google',
        'name' => 'Gemini 1.5 Pro (public preview)',
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent',
        'format' => 'text',
        'limits' => [
            'max_tokens' => 131072,
            'token_estimator' => 'gpt',
            'estimated_max_chars' => 524288
        ],
        'requirements' => [
            'auth' => [
                'help_url' => 'https://makersuite.google.com/app/apikey',
                'type' => 'query',
                'key_name' => 'key',
                'prefix' => null,
                'env' => 'GEMINI_API_KEY',
                'arg' => '--api-key-gemini'
            ],
            'headers' => ['Content-Type: application/json'],
            'body_type' => 'json',
            'params' => ['contents']
        ],
        'notes' => 'Modern LLM for flexible prompt-based translation, API available with free quota.',
        'defaults' => [
            'target_lang' => 'EN',
            'formality'   => null,
            'prompt_html' => null,
        ]
    ]
];
