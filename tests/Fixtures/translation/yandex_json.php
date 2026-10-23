<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled Yandex JSON replies for facade and batching integration tests. */
    class HttpClient
    {
        public static array $scenario = [];
        public static array $payloads = [];

        public static function request(...$arguments): array
        {
            $payload = json_decode($arguments['body'], true, 512, JSON_THROW_ON_ERROR);
            self::$payloads[] = $payload;
            $native = ($payload['jsonObject'] ?? false) === true;
            $user = $payload['messages'][1]['text'];
            if (!$native) {
                preg_match('/‹‹TRANSLATION››\n(.*?)\n‹‹END››/s', $user, $match);
                $user = $match[1];
            }
            if (array_key_exists('translated', self::$scenario)) {
                $json = self::$scenario['translated'];
            } else {
                $value = json_decode($user, false, 512, JSON_THROW_ON_ERROR);
                $json = json_encode(self::translate($value), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
            $content = $native ? $json : "‹‹TRANSLATION››\n" . $json . "\n‹‹END››";
            $body = json_encode(['result' => [
                'alternatives' => [['status' => self::$scenario['status'] ?? 'ALTERNATIVE_STATUS_FINAL',
                    'message' => ['text' => $content]]],
                'usage' => ['inputTextTokens' => '4', 'completionTokens' => '6', 'totalTokens' => '10'],
            ]], JSON_THROW_ON_ERROR);
            return ['body' => $body, 'status' => 200, 'headers' => [], 'debugRequest' => '', 'debugResponse' => ''];
        }

        private static function translate(mixed $value): mixed
        {
            if (is_string($value)) {
                return 'T:' . $value;
            }
            if ($value instanceof \stdClass) {
                foreach ($value as $key => $child) {
                    $value->{$key} = self::translate($child);
                }
            } elseif (is_array($value)) {
                $value = array_map(self::translate(...), $value);
            }
            return $value;
        }
    }
}

namespace {
    $scenario = json_decode((string) file_get_contents('php://stdin'), true, 512, JSON_THROW_ON_ERROR);
    \Bblslug\HttpClient::$scenario = $scenario;
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    try {
        $result = \Bblslug\Bblslug::translate(
            apiKey: 'fixture-key',
            modelKey: $scenario['model'] ?? 'yandex:gpt-lite',
            format: $scenario['format'] ?? 'json',
            text: $scenario['input'] ?? '{"message":"Hello","count":1}',
            validate: $scenario['validate'] ?? true,
            variables: ['folder_id' => 'fixture-folder', 'json_transport' => $scenario['transport'] ?? 'native'],
            htmlTransport: $scenario['htmlTransport'] ?? 'document',
            htmlJsonPointers: $scenario['htmlJsonPointers'] ?? [],
            htmlSegmentBatchSize: $scenario['batchSize'] ?? null,
        );
        $row = ['type' => 'success', 'result' => $result];
    } catch (\Throwable $exception) {
        $row = ['type' => $exception::class, 'message' => $exception->getMessage()];
    }
    $row['requests'] = \Bblslug\HttpClient::$payloads;
    echo json_encode($row, JSON_THROW_ON_ERROR);
}
