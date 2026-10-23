<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled OpenAI response and request recorder for reserved-variable tests. */
    class HttpClient
    {
        public static int $requestCount = 0;
        public static string $requestBody = '';

        /** @return array{body:string,debugRequest:string,debugResponse:string,headers:array<string,array<int,string>>,status:int} */
        public static function request(...$arguments): array
        {
            if ($arguments['dryRun'] ?? false) {
                return [
                    'status' => 0, 'body' => '[dry-run]', 'headers' => [],
                    'debugRequest' => '', 'debugResponse' => '',
                ];
            }
            self::$requestCount++;
            self::$requestBody = (string) ($arguments['body'] ?? '');

            return [
                'body' => json_encode([
                    'choices' => [['finish_reason' => 'stop', 'message' => [
                        'content' => "‹‹TRANSLATION››\nTranslated\n‹‹END››",
                    ]]],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'debugRequest' => '',
                'debugResponse' => '',
                'headers' => [],
                'status' => 200,
            ];
        }
    }
}

namespace {
    $scenario = json_decode((string) file_get_contents('php://stdin'), true, 512, JSON_THROW_ON_ERROR);

    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    try {
        $result = \Bblslug\Bblslug::translate(
            apiKey: 'fixture-key',
            format: (string) ($scenario['format'] ?? 'text'),
            modelKey: 'openai:gpt-5-mini',
            text: (string) ($scenario['text'] ?? 'Hello'),
            dryRun: (bool) ($scenario['dryRun'] ?? false),
            filters: $scenario['filters'] ?? [],
            validate: (bool) ($scenario['validate'] ?? true),
            variables: $scenario['variables'] ?? [],
        );
        echo json_encode([
            'type' => 'success',
            'result' => $result['result'],
            'requestCount' => \Bblslug\HttpClient::$requestCount,
            'requestBody' => \Bblslug\HttpClient::$requestBody,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    } catch (\Throwable $exception) {
        echo json_encode([
            'type' => $exception::class,
            'message' => $exception->getMessage(),
            'requestCount' => \Bblslug\HttpClient::$requestCount,
            'requestBody' => \Bblslug\HttpClient::$requestBody,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    }
}
