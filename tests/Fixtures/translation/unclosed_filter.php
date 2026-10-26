<?php

declare(strict_types=1);

namespace Bblslug {
    class HttpClient
    {
        public static int $requests = 0;

        /** @return array{body:string,debugRequest:string,debugResponse:string,headers:array<string,array<int,string>>,status:int} */
        public static function request(...$arguments): array
        {
            self::$requests++;
            return [
                'body' => '{"choices":[{"finish_reason":"stop","message":'
                    . '{"content":"‹‹TRANSLATION››\\ntranslated\\n‹‹END››"}}]}',
                'debugRequest' => '',
                'debugResponse' => '',
                'headers' => [],
                'status' => 200,
            ];
        }
    }
}

namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    if (getenv('BBLSLUG_UNCLOSED_FILTER_CLI') === '1') {
        \Bblslug\Console\Cli::run();
        exit(0);
    }

    $scenario = json_decode((string) file_get_contents('php://stdin'), true, 512, JSON_THROW_ON_ERROR);

    try {
        $result = \Bblslug\Bblslug::translate(
            apiKey: 'fixture-key',
            format: (string) ($scenario['format'] ?? 'html'),
            modelKey: 'openai:gpt-5-mini',
            text: (string) ($scenario['input'] ?? ''),
            filters: $scenario['filters'] ?? [],
            validate: (bool) ($scenario['validate'] ?? true),
            dryRun: (bool) ($scenario['dryRun'] ?? false),
            validateEmbeddedJson: (bool) ($scenario['validateEmbeddedJson'] ?? false)
        );
        echo json_encode([
            'type' => 'success',
            'result' => $result['result'],
            'requests' => \Bblslug\HttpClient::$requests,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    } catch (\Throwable $exception) {
        echo json_encode([
            'type' => $exception::class,
            'message' => $exception->getMessage(),
            'requests' => \Bblslug\HttpClient::$requests,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    }
}
