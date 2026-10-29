<?php

declare(strict_types=1);

namespace Bblslug {
    final class Bblslug
    {
        public static function translate(...$args): array
        {
            if (getenv('PROMPT_TEST_FAILURE') === 'exception') {
                throw new \RuntimeException('Authorization: secret-test-key');
            }
            $prompt = $args['promptKey'];
            $custom = [];
            if ($prompt === 'app_owned') {
                foreach (['json', 'structured_json'] as $format) {
                    $custom[$format] = \Bblslug\Models\Prompts::render($prompt, $format, []);
                }
            }
            file_put_contents(getenv('PROMPT_TEST_LOG'), json_encode([
                'pid' => getmypid(), 'context' => $args['context'], 'prompt' => $prompt, 'custom' => $custom,
            ], JSON_THROW_ON_ERROR) . "\n", FILE_APPEND);
            $term = str_contains($args['context'], 'Projektbereich') || $prompt === 'app_owned'
                ? 'Projektbereich' : 'Arbeitsbereich';
            if (getenv('PROMPT_TEST_FAILURE') === 'term') {
                $term = 'wrong';
            }
            return ['httpStatus' => 200, 'result' => json_encode(['message' => $term, 'count' => 2,
                'enabled' => true, 'nested' => ['message' => $term]], JSON_THROW_ON_ERROR)];
        }
    }
}

namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    require dirname(__DIR__, 3) . '/tools/smoke/Failure.php';
    require dirname(__DIR__, 3) . '/tools/smoke/Prompts.php';
    \Bblslug\Tools\Smoke\Prompts::worker(['model' => 'openai:gpt-6-luna', 'case' => $argv[1]]);
}
