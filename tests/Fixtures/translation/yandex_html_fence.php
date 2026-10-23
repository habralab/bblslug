<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled Yandex response for HTML fence compatibility tests. */
    class HttpClient
    {
        public static string $responseBody = '';

        /** @return array{body:string,debugRequest:string,debugResponse:string,headers:array<string,array<int,string>>,status:int} */
        public static function request(
            string $method,
            string $url,
            string $body = '',
            bool $dryRun = false,
            array $headers = [],
            array $maskPatterns = [],
            ?string $proxy = null,
            bool $verbose = false,
            ?int $timeoutSeconds = null,
            ?int $connectTimeoutSeconds = null
        ): array {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $messages = $payload['messages'] ?? [];
            $user = '';
            foreach ($messages as $message) {
                if (($message['role'] ?? null) === 'user') {
                    $user = (string) ($message['text'] ?? $message['content'] ?? '');
                    break;
                }
            }
            if (!preg_match('/‹‹TRANSLATION››\n(.*?)\n‹‹END››/s', $user, $matches)) {
                throw new \RuntimeException('Fixture could not find Yandex translation markers');
            }

            $scenario = $GLOBALS['yandex_html_fence_scenario'] ?? [];
            $translated = (string) $matches[1];
            if (($scenario['mutation'] ?? '') === 'drop_tag') {
                $translated = str_replace(['<p>', '</p>'], ['<section>', '</section>'], $translated);
            } elseif (($scenario['mutation'] ?? '') === 'missing_placeholder') {
                $translated = preg_replace('/@@[0-9]+@@/', '', $translated, 1) ?? $translated;
            }
            if (isset($scenario['response'])) {
                $translated = (string) $scenario['response'];
            } else {
                $translated = (string) ($scenario['prefix'] ?? "```\n") . $translated;
            }

            self::$responseBody = (string) json_encode([
                'result' => [
                    'alternatives' => [[
                        'status' => 'ALTERNATIVE_STATUS_FINAL',
                        'message' => ['text' => "‹‹TRANSLATION››\n{$translated}\n‹‹END››"],
                    ]],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            return [
                'body' => self::$responseBody,
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
    $GLOBALS['yandex_html_fence_scenario'] = $scenario;

    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    try {
        $result = \Bblslug\Bblslug::translate(
            apiKey: 'fixture-key',
            format: 'html',
            modelKey: 'yandex:gpt-lite',
            text: (string) ($scenario['input'] ?? '<p>Hello</p>'),
            filters: $scenario['filters'] ?? ['url', 'html_pre'],
            validate: (bool) ($scenario['validate'] ?? true),
            variables: ['folder_id' => 'b1folder'],
            verbose: false
        );
        echo json_encode([
            'type' => 'success',
            'result' => $result['result'],
            'prepared' => $result['prepared'],
            'rawResponseBody' => $result['rawResponseBody'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    } catch (\Throwable $exception) {
        echo json_encode([
            'type' => $exception::class,
            'message' => $exception->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    }
}
