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

    'openai:gpt-4' => [
        'vendor' => 'openai',
        'name' => 'OpenAI GPT-4',
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
        'notes' => 'Classic GPT-4 model: highest reliability.',
        'defaults' => [
            'model'       => 'gpt-4',
            'temperature' => 0.0,
        ]
    ],

    'openai:gpt-4-turbo' => [
        'vendor' => 'openai',
        'name' => 'OpenAI GPT-4 Turbo',
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
        'notes' => 'Fast & cost-effective GPT-4 quality.',
        'defaults' => [
            'model'       => 'gpt-4-turbo',
            'temperature' => 0.0,
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

    'openai:gpt-4o-mini' => [
        'vendor' => 'openai',
        'name' => 'OpenAI GPT-4o Mini',
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
        'notes' => 'Lightweight GPT-4o: lower latency/cost.',
        'defaults' => [
            'model'       => 'gpt-4o-mini',
            'temperature' => 0.0,
        ]
    ],

    'gemini:2.0-flash' => [
        'vendor'       => 'google',
        'name'         => 'Gemini 2.0 Flash',
        'endpoint'     => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
        'format'       => 'text',
        'limits'       => [
            'max_tokens'           => 131072,
            'token_estimator'      => 'gpt',
            'estimated_max_chars'  => 524288,
        ],
        'requirements' => [
            'auth'     => [
                'help_url' => 'https://makersuite.google.com/app/apikey',
                'type'     => 'header',
                'key_name' => 'x-goog-api-key',
                'prefix'   => null,
                'env'      => 'GEMINI_API_KEY',
                'arg'      => '--api-key-gemini'
            ],
            'headers'  => ['Content-Type: application/json'],
            'body_type'=> 'json',
            'params'   => ['system_instruction','contents','generationConfig']
        ],
        'notes'        => 'Low-latency Flash model, balanced cost and performance.',
        'defaults'     => [
            'model'           => 'gemini-2.0-flash',
            'temperature'     => 0.0,
            'candidateCount'  => 1,
            'maxOutputTokens' => null,
        ]
    ],

    'gemini:2.5-flash' => [
        'vendor'       => 'google',
        'name'         => 'Gemini 2.5 Flash',
        'endpoint'     => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent',
        'format'       => 'text',
        'limits'       => [
            'max_tokens'           => 131072,
            'token_estimator'      => 'gpt',
            'estimated_max_chars'  => 524288,
        ],
        'requirements' => [
            'auth'     => [
                'help_url' => 'https://makersuite.google.com/app/apikey',
                'type'     => 'header',
                'key_name' => 'x-goog-api-key',
                'prefix'   => null,
                'env'      => 'GEMINI_API_KEY',
                'arg'      => '--api-key-gemini'
            ],
            'headers'  => ['Content-Type: application/json'],
            'body_type'=> 'json',
            'params'   => ['system_instruction','contents','generationConfig']
        ],
        'notes'        => 'High-performance Flash model with chain-of-thought support.',
        'defaults'     => [
            'model'           => 'gemini-2.5-flash',
            'temperature'     => 0.0,
            'candidateCount'  => 1,
            'maxOutputTokens' => null,
            'thinkingBudget'  => null,
            'includeThoughts' => null,
        ]
    ],

    'gemini:2.5-flash-lite' => [
        'vendor'       => 'google',
        'name'         => 'Gemini 2.5 Flash-Lite',
        'endpoint'     => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent',
        'format'       => 'text',
        'limits'       => [
            'max_tokens'           => 131072,
            'token_estimator'      => 'gpt',
            'estimated_max_chars'  => 524288,
        ],
        'requirements' => [
            'auth'     => [
                'help_url' => 'https://makersuite.google.com/app/apikey',
                'type'     => 'header',
                'key_name' => 'x-goog-api-key',
                'prefix'   => null,
                'env'      => 'GEMINI_API_KEY',
                'arg'      => '--api-key-gemini'
            ],
            'headers'  => ['Content-Type: application/json'],
            'body_type'=> 'json',
            'params'   => ['system_instruction','contents','generationConfig']
        ],
        'notes'        => 'Ultra-fast, cost-optimized Flash-Lite variant.',
        'defaults'     => [
            'model'           => 'gemini-2.5-flash-lite',
            'temperature'     => 0.0,
            'candidateCount'  => 1,
            'maxOutputTokens' => null,
            'thinkingBudget'  => null,
            'includeThoughts' => null,
        ]
    ],

    'gemini:2.5-pro' => [
        'vendor'       => 'google',
        'name'         => 'Gemini 2.5 Pro',
        'endpoint'     => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent',
        'format'       => 'text',
        'limits'       => [
            'max_tokens'           => 262144,
            'token_estimator'      => 'gpt',
            'estimated_max_chars'  => 1048576,
        ],
        'requirements' => [
            'auth'     => [
                'help_url' => 'https://makersuite.google.com/app/apikey',
                'type'     => 'header',
                'key_name' => 'x-goog-api-key',
                'prefix'   => null,
                'env'      => 'GEMINI_API_KEY',
                'arg'      => '--api-key-gemini'
            ],
            'headers'  => ['Content-Type: application/json'],
            'body_type'=> 'json',
            'params'   => ['system_instruction','contents','generationConfig']
        ],
        'notes'        => 'Top-tier Pro model for longest contexts and highest accuracy.',
        'defaults'     => [
            'model'           => 'gemini-2.5-pro',
            'temperature'     => 0.0,
            'candidateCount'  => 1,
            'maxOutputTokens' => null,
            'thinkingBudget'  => null,
            'includeThoughts' => null,
        ]
    ],
];
