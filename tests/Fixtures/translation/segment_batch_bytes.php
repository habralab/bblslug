<?php

declare(strict_types=1);

namespace Bblslug {
    class HttpClient
    {
        public static array $scenario = [];
        public static array $packets = [];
        public static int $networkCalls = 0;

        public static function request(...$arguments): array
        {
            if (($arguments['dryRun'] ?? false) === true) {
                return self::response('[dry-run]');
            }
            self::$networkCalls++;
            $body = (string) ($arguments['body'] ?? '');
            $request = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $model = self::$scenario['model'] ?? 'openai:gpt-5-mini';
            if (str_starts_with($model, 'deepl:')) {
                $packet = [];
                foreach (($request['text'] ?? []) as $index => $value) {
                    $packet['s' . (count(self::$packets) * 2 + $index)] = $value;
                }
            } else {
                $content = $request['messages'][1]['content'] ?? $request['messages'][1]['text']
                    ?? $request['contents'][0]['parts'][0]['text'] ?? '{}';
                $content = trim((string) preg_replace('/^‹‹TRANSLATION››\s*|\s*‹‹END››$/u', '', $content));
                $packet = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            }
            self::$packets[] = $packet;
            $translated = (self::$scenario['identity'] ?? false) ? $packet
                : array_map(static fn (mixed $value): string => 'T:' . (string) $value, $packet);
            if (isset(self::$scenario['translated'])) {
                $allTranslations = json_decode((string) self::$scenario['translated'], true, 512, JSON_THROW_ON_ERROR);
                $translated = array_intersect_key($allTranslations, $packet);
            }
            $number = count(self::$packets);
            $failure = $number === (self::$scenario['failAt'] ?? null) ? (self::$scenario['failure'] ?? '') : '';
            if ($failure === 'missing') {
                array_pop($translated);
            }
            if ($failure === 'extra') {
                $translated['s9999'] = 'unexpected';
            }
            if ($failure === 'type') {
                $translated[array_key_first($translated)] = null;
            }
            if ($failure === 'empty') {
                $translated[array_key_first($translated)] = '';
            }
            if ($failure === 'newline') {
                $translated[array_key_first($translated)] .= "\nintroduced";
            }
            if ($failure === 'marker') {
                $translated[array_key_first($translated)] .= ' @@0@@';
            }
            $json = json_encode((object) $translated, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if ($failure === 'duplicate') {
                $key = array_key_first($translated);
                $json = substr($json, 0, -1) . ',"' . $key . '":"duplicate"}';
            }
            if ($failure === 'http') {
                return self::response('{"error":{"message":"controlled decline"}}', 402);
            }
            if ($failure === 'truncation') {
                return self::response(json_encode([
                    'choices' => [['finish_reason' => 'length', 'message' => ['content' => $json]]],
                ], JSON_THROW_ON_ERROR));
            }
            if (str_starts_with($model, 'yandex:')) {
                return self::response(json_encode([
                    'result' => ['alternatives' => [[
                        'status' => 'ALTERNATIVE_STATUS_FINAL', 'message' => ['text' => $json],
                    ]]],
                    'usage' => ['inputTextTokens' => '4', 'completionTokens' => '6', 'totalTokens' => '10'],
                ], JSON_THROW_ON_ERROR));
            }
            if (str_starts_with($model, 'google:')) {
                return self::response(json_encode([
                    'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => $json]]]]],
                    'usageMetadata' => ['totalTokenCount' => 10, 'promptTokenCount' => 4, 'candidatesTokenCount' => 6],
                ], JSON_THROW_ON_ERROR));
            }
            if (str_starts_with($model, 'deepl:')) {
                $values = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                return self::response(json_encode([
                    'translations' => array_map(
                        static fn (mixed $value): array => ['text' => $value],
                        array_values($values)
                    ),
                ], JSON_THROW_ON_ERROR));
            }
            return self::response(json_encode([
                'choices' => [['finish_reason' => 'stop', 'message' => [
                    'content' => "‹‹TRANSLATION››\n{$json}\n‹‹END››",
                ]]],
                'usage' => ['total_tokens' => 10, 'prompt_tokens' => 4, 'completion_tokens' => 6],
            ], JSON_THROW_ON_ERROR));
        }

        private static function response(string $body, int $status = 200): array
        {
            return ['body' => $body, 'status' => $status, 'headers' => [], 'debugRequest' => '', 'debugResponse' => ''];
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
            format: $scenario['format'] ?? 'text',
            modelKey: $scenario['model'] ?? 'openai:gpt-5-mini',
            text: $scenario['input'] ?? "one\ntwo\nthree",
            filters: $scenario['filters'] ?? [],
            variables: $scenario['variables'] ?? [],
            context: $scenario['context'] ?? null,
            validate: $scenario['validate'] ?? true,
            dryRun: $scenario['dryRun'] ?? false,
            htmlTransport: $scenario['htmlTransport'] ?? 'document',
            htmlJsonPointers: $scenario['htmlJsonPointers'] ?? [],
            htmlSegmentBatchSize: $scenario['htmlSegmentBatchSize'] ?? null,
            textTransport: $scenario['textTransport'] ?? 'segments',
            textSegmentBatchSize: $scenario['textSegmentBatchSize'] ?? null,
            segmentBatchMaxBytes: $scenario['segmentBatchMaxBytes'] ?? null
        );
        $output = ['type' => 'success', 'result' => $result];
    } catch (\Throwable $exception) {
        $output = ['type' => $exception::class, 'message' => $exception->getMessage()];
    }
    $output['packets'] = \Bblslug\HttpClient::$packets;
    $output['networkCalls'] = \Bblslug\HttpClient::$networkCalls;
    echo json_encode($output, JSON_THROW_ON_ERROR);
}
