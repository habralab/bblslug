<?php

declare(strict_types=1);

namespace Bblslug {
    /** Deterministic HTTP failure stub for CLI provider-failure checks. */
    class HttpClient
    {
        /** @return array{body:string,debugRequest:string,debugResponse:string,headers:array<string,array<int,string>>,status:int} */
        public static function request(...$arguments): array
        {
            $case = getenv('BBLSLUG_TEST_CASE') ?: '';
            if ($case === 'timeout') {
                throw new \RuntimeException('Network error: operation timed out', 28);
            }

            $key = getenv('BBLSLUG_OPENAI_API_KEY') ?: getenv('BBLSLUG_ANTHROPIC_API_KEY') ?: '';
            $body = '';
            $status = 500;
            switch ($case) {
                case 'http401':
                    $status = 401;
                    $body = json_encode(['error' => ['message' => "Invalid API key {$key}"]], JSON_THROW_ON_ERROR);
                    break;
                case 'http429':
                    $status = 429;
                    $body = json_encode(['error' => ['message' => "Rate limit for {$key}"]], JSON_THROW_ON_ERROR);
                    break;
                case 'anthropic-credit':
                    $status = 400;
                    $body = json_encode([
                        'type' => 'error',
                        'error' => ['type' => 'invalid_request_error', 'message' => "Credit balance too low ({$key})"],
                    ], JSON_THROW_ON_ERROR);
                    break;
                case 'yandex-truncated':
                case 'yandex-filtered':
                    $status = 200;
                    $body = json_encode(['result' => ['alternatives' => [[
                        'status' => $case === 'yandex-truncated'
                            ? 'ALTERNATIVE_STATUS_TRUNCATED_FINAL' : 'ALTERNATIVE_STATUS_CONTENT_FILTER',
                        'message' => ['text' => '‹‹TRANSLATION››Hallo‹‹END››'],
                    ]]]], JSON_THROW_ON_ERROR);
                    break;
                case 'malformed':
                    $status = 200;
                    $body = '{}';
                    break;
                case 'openai-filtered':
                case 'xai-tool-call':
                    $status = 200;
                    $body = json_encode(['choices' => [[
                        'finish_reason' => $case === 'openai-filtered' ? 'content_filter' : 'tool_calls',
                        'message' => ['content' => '‹‹TRANSLATION››partial‹‹END››'],
                    ]]], JSON_THROW_ON_ERROR);
                    break;
                case 'cleared-html':
                    $status = 200;
                    $body = json_encode(['choices' => [[
                        'finish_reason' => 'stop',
                        'message' => ['content' => "‹‹TRANSLATION››\n<p></p>\n‹‹END››"],
                    ]]], JSON_THROW_ON_ERROR);
                    break;
                case 'empty-translation':
                case 'whitespace-translation':
                    $status = 200;
                    $translation = $case === 'empty-translation' ? '' : " \n \t";
                    $body = json_encode(['choices' => [[
                        'finish_reason' => 'stop',
                        'message' => ['content' => "‹‹TRANSLATION››\n{$translation}\n‹‹END››"],
                    ]]], JSON_THROW_ON_ERROR);
                    break;
            }

            return [
                'body' => $body,
                'debugRequest' => '',
                'debugResponse' => '',
                'headers' => [],
                'status' => $status,
            ];
        }
    }
}

namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    \Bblslug\Console\Cli::run();
}
