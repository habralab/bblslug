<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled provider response for the full structured translation path. */
    class HttpClient
    {
        public static string $response = '';
        public static int $calls = 0;
        public static string $requestBody = '';

        /** @return array{body:string,debugRequest:string,debugResponse:string,headers:array<string,array<int,string>>,status:int} */
        public static function request(...$arguments): array
        {
            self::$calls++;
            self::$requestBody = (string) ($arguments['body'] ?? '');
            return [
                'body' => self::$response,
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
    $translated = (string) ($scenario['translated'] ?? '');
    $model = (string) ($scenario['model'] ?? 'openai:gpt-5-mini');
    $response = str_starts_with($model, 'google:')
        ? ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [
            ['thought' => true, 'text' => 'Reasoning that must not enter the JSON.'],
            ['text' => $translated],
        ]]]]]
        : ['choices' => [['finish_reason' => 'stop', 'message' => ['content' => $translated]]]];
    if (str_starts_with($model, 'yandex:')) {
        $response = ['result' => ['alternatives' => [[
            'status' => 'ALTERNATIVE_STATUS_FINAL', 'message' => ['text' => $translated],
        ]]]];
    }
    \Bblslug\HttpClient::$response = json_encode($response, JSON_THROW_ON_ERROR);
    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    try {
        $result = \Bblslug\Bblslug::translate(
            apiKey: 'fixture-key',
            format: (string) ($scenario['format'] ?? 'structured_json'),
            modelKey: $model,
            text: (string) ($scenario['input'] ?? ''),
            validate: (bool) ($scenario['validate'] ?? true),
            variables: ($scenario['variables'] ?? ['response_schema' => $scenario['schema'] ?? [
                'type' => 'object',
                'properties' => ['message' => ['type' => 'string']],
                'required' => ['message'],
                'additionalProperties' => false,
            ]]) + ['folder_id' => 'fixture-folder'],
            validateEmbeddedJson: (bool) ($scenario['validateEmbeddedJson'] ?? false),
            validateHtmlJsonPointers: $scenario['validateHtmlJsonPointers'] ?? []
        );
        echo json_encode([
            'type' => 'success',
            'httpCalls' => \Bblslug\HttpClient::$calls,
            'result' => $result['result'],
            'request' => json_decode(\Bblslug\HttpClient::$requestBody, true, 512, JSON_THROW_ON_ERROR),
        ], JSON_THROW_ON_ERROR), "\n";
    } catch (\Throwable $exception) {
        echo json_encode(
            [
                'type' => $exception::class,
                'httpCalls' => \Bblslug\HttpClient::$calls,
                'message' => $exception->getMessage(),
            ],
            JSON_THROW_ON_ERROR
        ), "\n";
    }
}
