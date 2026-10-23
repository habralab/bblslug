<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled responses for the facade's HTML segment transport. */
    class HttpClient
    {
        public static string $response = '';
        public static string $request = '';
        public static int $networkCalls = 0;

        /** @return array{body:string,debugRequest:string,debugResponse:string,headers:array<string,array<int,string>>,status:int} */
        public static function request(...$arguments): array
        {
            self::$request = (string) ($arguments['body'] ?? '');
            if (!($arguments['dryRun'] ?? false)) {
                self::$networkCalls++;
            }
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
    $model = $scenario['model'] ?? 'openai:gpt-5-mini';
    $translated = $scenario['translated'] ?? '{"s0":"Bonjour"}';
    if (str_starts_with($model, 'google:')) {
        $response = ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [
            ['text' => $translated],
        ]]]]];
    } elseif (str_starts_with($model, 'deepl:')) {
        $response = ['translations' => array_map(
            static fn (mixed $value): array => ['text' => $value],
            array_values(json_decode($translated, true, 512, JSON_THROW_ON_ERROR))
        )];
    } else {
        $response = ['choices' => [['finish_reason' => 'stop', 'message' => [
            'content' => "‹‹TRANSLATION››\n" . $translated . "\n‹‹END››",
        ]]]];
    }
    \Bblslug\HttpClient::$response = json_encode($response, JSON_THROW_ON_ERROR);
    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    try {
        $result = \Bblslug\Bblslug::translate(
            apiKey: 'fixture-key',
            format: $scenario['format'] ?? 'html',
            modelKey: $model,
            text: $scenario['input'] ?? '<p>Hello</p>',
            filters: $scenario['filters'] ?? [],
            variables: $scenario['variables'] ?? [],
            validate: $scenario['validate'] ?? true,
            dryRun: $scenario['dryRun'] ?? false,
            htmlTransport: $scenario['htmlTransport'] ?? 'segments',
            htmlJsonPointers: $scenario['htmlJsonPointers'] ?? [],
            validateHtmlJsonPointers: $scenario['validateHtmlJsonPointers'] ?? []
        );
        echo json_encode([
            'type' => 'success',
            'result' => $result['result'],
            'prepared' => $result['prepared'],
            'filterStats' => $result['filterStats'],
            'rawResponseBody' => $result['rawResponseBody'],
            'networkCalls' => \Bblslug\HttpClient::$networkCalls,
            'request' => json_decode(\Bblslug\HttpClient::$request, true),
        ], JSON_THROW_ON_ERROR);
    } catch (\Throwable $exception) {
        echo json_encode([
            'type' => $exception::class,
            'message' => $exception->getMessage(),
            'networkCalls' => \Bblslug\HttpClient::$networkCalls,
        ], JSON_THROW_ON_ERROR);
    }
}
