<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled OpenAI response for JSON facade validation tests. */
    class HttpClient
    {
        public static string $translated = '';

        /** @return array{body:string,debugRequest:string,debugResponse:string,headers:array<string,array<int,string>>,status:int} */
        public static function request(...$arguments): array
        {
            return [
                'body' => (string) json_encode([
                    'choices' => [['finish_reason' => 'stop', 'message' => [
                        'content' => "‹‹TRANSLATION››\n" . self::$translated . "\n‹‹END››",
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
    \Bblslug\HttpClient::$translated = (string) ($scenario['translated'] ?? '');

    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    try {
        $result = \Bblslug\Bblslug::translate(
            apiKey: 'fixture-key',
            format: (string) ($scenario['format'] ?? 'json'),
            modelKey: 'openai:gpt-5-mini',
            text: (string) ($scenario['input'] ?? ''),
            filters: [],
            validate: (bool) ($scenario['validate'] ?? true),
            verbose: true,
            validateEmbeddedJson: (bool) ($scenario['validateEmbeddedJson'] ?? false)
        );
        echo json_encode([
            'type' => 'success',
            'result' => $result['result'],
            'rawResponseBody' => $result['rawResponseBody'],
            'lengths' => $result['lengths'],
            'debugResponse' => $result['debugResponse'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    } catch (\Throwable $exception) {
        echo json_encode([
            'type' => $exception::class,
            'message' => $exception->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    }
}
