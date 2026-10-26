<?php

declare(strict_types=1);

namespace Bblslug {
    /** Deterministic fake for DeepL's multipart document workflow. */
    class HttpClient
    {
        public static array $scenario = [];
        public static array $calls = [];
        public static string $translated = '{}';

        public static function request(...$arguments): array
        {
            self::$calls[] = $arguments;
            if (($arguments['dryRun'] ?? false) === true) {
                return [
                    'status' => 0, 'body' => '[dry-run]', 'headers' => [],
                    'debugRequest' => '', 'debugResponse' => '',
                ];
            }
            $n = count(self::$calls);
            usleep((int) (self::$scenario['delayMilliseconds'][$n] ?? 0) * 1000);
            $failure = self::$scenario['failure'] ?? null;
            if (is_array($failure) && ($failure['call'] ?? 0) === $n && isset($failure['throwCode'])) {
                $masker = new \Bblslug\SecretMasker($arguments['maskPatterns'] ?? []);
                throw new \RuntimeException(
                    $masker->redact((string) ($failure['body'] ?? 'Network timeout')),
                    (int) $failure['throwCode']
                );
            }
            $status = is_array($failure) && (($failure['call'] ?? 0) === $n) ? (int)($failure['status'] ?? 500) : 200;
            $url = (string)($arguments['url'] ?? '');
            $body = (string)($arguments['body'] ?? '');
            $auth = 'fixture-document-key-secret';
            $debugRequest = "POST {$url}\nAuthorization: DeepL-Auth-Key fixture-api-secret\n{$body}";
            if (str_ends_with($url, '/document')) {
                $translated = self::translateMultipart($body);
                self::$translated = $translated;
                $response = json_encode(
                    ['document_id' => 'fixture-upload-id', 'document_key' => $auth],
                    JSON_THROW_ON_ERROR
                );
            } elseif (str_ends_with($url, '/result')) {
                $response = is_string(self::$scenario['downloadRaw'] ?? null)
                    ? self::$scenario['downloadRaw']
                    : (is_string(self::$scenario['download'] ?? null)
                        ? self::$scenario['download']
                        : self::$translated);
                if (isset(self::$scenario['downloadRaw'])) {
                    $response = (string) self::$scenario['downloadRaw'];
                }
            } else {
                $statuses = self::$scenario['statuses'] ?? ['done'];
                $statusValue = $statuses[min($n - 2, count($statuses) - 1)] ?? 'done';
                $response = json_encode(['status' => $statusValue, 'billed_characters' => 17], JSON_THROW_ON_ERROR);
            }
            if (is_array($failure) && (($failure['call'] ?? 0) === $n)) {
                $response = $n === 1
                    ? '{"document_id":"fixture-upload-id","document_key":"fixture-document-key-secret"}'
                    : (string)($failure['body'] ?? 'provider failure');
            }
            $debugResponse = "response {$status}: {$response} {$auth}";
            $patterns = (array)($arguments['maskPatterns'] ?? []);
            foreach ($patterns as $pattern) {
                if ($pattern !== '') {
                    $debugRequest = str_replace((string)$pattern, '***', $debugRequest);
                    $debugResponse = str_replace((string)$pattern, '***', $debugResponse);
                }
            }
            return [
                'status' => $status, 'body' => $response, 'headers' => [],
                'debugRequest' => $debugRequest, 'debugResponse' => $debugResponse,
            ];
        }

        private static function translateMultipart(string $body): string
        {
            $pattern = '/name="file"; filename="translation\.json"\r\n'
                . 'Content-Type: application\/json\r\n\r\n(.*?)\r\n--/s';
            if (preg_match($pattern, $body, $m)) {
                $value = $m[1];
                return str_replace(['Hello', 'World'], ['Hallo', 'Welt'], $value);
            }
            return '{}';
        }
    }
}

namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    $scenario = json_decode(file_get_contents('php://stdin'), true, 512, JSON_THROW_ON_ERROR);
    \Bblslug\HttpClient::$scenario = $scenario;
    try {
        $result = \Bblslug\Bblslug::translate(
            apiKey: 'fixture-api-secret',
            format: $scenario['format'] ?? 'json',
            modelKey: 'deepl:free',
            text: (string)$scenario['source'],
            filters: $scenario['filters'] ?? [],
            variables: $scenario['variables'] ?? [],
            context: $scenario['context'] ?? null,
            proxy: $scenario['proxy'] ?? null,
            dryRun: (bool)($scenario['dryRun'] ?? false),
            verbose: (bool)($scenario['verbose'] ?? true),
            validateEmbeddedJson: in_array(
                $scenario['format'] ?? 'json',
                ['json', 'linedJson', 'structured_json'],
                true
            ),
            operationTimeoutSeconds: $scenario['operationTimeoutSeconds'] ?? null,
            htmlTransport: $scenario['htmlTransport'] ?? 'document',
            htmlSegmentBatchSize: $scenario['htmlSegmentBatchSize'] ?? null
        );
        echo json_encode([
            'type' => 'success',
            'result' => $result['result'],
            'httpStatus' => $result['httpStatus'],
            'calls' => \Bblslug\HttpClient::$calls,
            'rawResponseBody' => $result['rawResponseBody'] ?? null,
            'debugRequest' => $result['debugRequest'] ?? '',
            'debugResponse' => $result['debugResponse'] ?? '',
        ], JSON_THROW_ON_ERROR);
    } catch (\Throwable $e) {
        echo json_encode(
            ['type' => $e::class, 'message' => $e->getMessage(), 'code' => $e->getCode(),
                'previous' => $e->getPrevious()?->getMessage(), 'calls' => \Bblslug\HttpClient::$calls],
            JSON_THROW_ON_ERROR
        );
    }
}
