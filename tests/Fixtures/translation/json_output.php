<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled OpenAI response for JSON facade validation tests. */
    class HttpClient
    {
        public static string $translated = '';
        public static int $calls = 0;
        public static bool $echoPrepared = false;
        public static bool $checkEmbedded = false;
        /** @var array<string,mixed>|null */
        public static ?array $expectedSchema = null;

        /** @return array{body:string,debugRequest:string,debugResponse:string,headers:array<string,array<int,string>>,status:int} */
        public static function request(...$arguments): array
        {
            self::$calls++;
            $request = json_decode((string) ($arguments['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            $schema = $request['response_format']['json_schema']['schema'] ?? null;
            if (self::$expectedSchema !== null && $schema !== self::$expectedSchema) {
                throw new \RuntimeException('Request schema changed');
            }
            if (self::$echoPrepared) {
                $content = (string) ($request['messages'][1]['content'] ?? '');
                self::$translated = trim((string) preg_replace('/^‹‹TRANSLATION››\s*|\s*‹‹END››$/u', '', $content));
                $prepared = json_decode(self::$translated, true, 512, JSON_THROW_ON_ERROR);
                if (self::$checkEmbedded) {
                    json_decode($prepared['embedded'], true, 512, JSON_THROW_ON_ERROR);
                }
            }
            return [
                'body' => (string) json_encode([
                    'choices' => [['finish_reason' => 'stop', 'message' => [
                        'content' => $schema !== null ? self::$translated
                            : "‹‹TRANSLATION››\n" . self::$translated . "\n‹‹END››",
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
    \Bblslug\HttpClient::$echoPrepared = (bool) ($scenario['echoPrepared'] ?? false);
    \Bblslug\HttpClient::$checkEmbedded = (bool) ($scenario['checkEmbedded'] ?? false);
    \Bblslug\HttpClient::$expectedSchema = $scenario['variables']['response_schema'] ?? null;

    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    try {
        $result = \Bblslug\Bblslug::translate(
            apiKey: 'fixture-key',
            format: (string) ($scenario['format'] ?? 'json'),
            modelKey: 'openai:gpt-5-mini',
            text: (string) ($scenario['input'] ?? ''),
            filters: $scenario['filters'] ?? [],
            validate: (bool) ($scenario['validate'] ?? true),
            verbose: true,
            variables: $scenario['variables'] ?? [],
            validateEmbeddedJson: (bool) ($scenario['validateEmbeddedJson'] ?? false),
            validateHtmlJsonPointers: $scenario['validateHtmlJsonPointers'] ?? [],
            dryRun: (bool) ($scenario['dryRun'] ?? false)
        );
        echo json_encode([
            'type' => 'success',
            'httpCalls' => \Bblslug\HttpClient::$calls,
            'result' => $result['result'],
            'rawResponseBody' => $result['rawResponseBody'],
            'lengths' => $result['lengths'],
            'debugResponse' => $result['debugResponse'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    } catch (\Throwable $exception) {
        echo json_encode([
            'type' => $exception::class, 'httpCalls' => \Bblslug\HttpClient::$calls,
            'message' => $exception->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    }
}
