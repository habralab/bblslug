<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled provider responses and native document continuations for batching. */
    class HttpClient
    {
        public static array $scenario = [];
        public static array $requests = [];
        public static array $packets = [];
        public static array $bodies = [];
        private static array $documents = [];

        public static function request(...$arguments): array
        {
            $url = (string) $arguments['url'];
            $body = (string) $arguments['body'];
            $dryRun = (bool) ($arguments['dryRun'] ?? false);
            if ($dryRun) {
                return self::response('[dry-run]', 0);
            }
            self::$requests[] = $url;
            if (str_ends_with($url, '/document')) {
                preg_match('~Content-Type: application/json\r\n\r\n(.*?)\r\n--~s', $body, $file);
                $packet = json_decode($file[1], true, 512, JSON_THROW_ON_ERROR);
                $id = 'doc' . (count(self::$documents) + 1);
                self::$documents[$id] = $packet;
                self::$packets[] = $packet;
                return self::response(json_encode(['document_id' => $id, 'document_key' => 'key-' . $id]));
            }
            if (preg_match('~/document/(doc[0-9]+)(/result)?$~', $url, $match)) {
                parse_str($body, $options);
                if (($options['document_key'] ?? null) !== 'key-' . $match[1]) {
                    throw new \RuntimeException('Document context leaked between batches');
                }
                if (!isset($match[2])) {
                    return self::response('{"status":"done"}');
                }
                return self::response(json_encode((object) self::translate(self::$documents[$match[1]])));
            }
            $request = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            self::$bodies[] = $request;
            $content = $request['messages'][1]['content'] ?? $request['messages'][1]['text']
                ?? $request['input'][1]['content'] ?? $request['contents'][0]['parts'][0]['text'] ?? '{}';
            if (str_starts_with($content, '‹‹TRANSLATION››')) {
                $content = trim(substr($content, strlen('‹‹TRANSLATION››'), -strlen('‹‹END››')));
            }
            $packet = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            self::$packets[] = $packet;
            $translated = self::translate($packet);
            $number = count(self::$packets);
            $failure = $number === (self::$scenario['failAt'] ?? null) ? self::$scenario['failure'] : '';
            if ($failure === 'http') {
                return self::response('{"error":{"message":"controlled decline"}}', 402);
            }
            if ($failure === 'missing') {
                array_pop($translated);
            } elseif ($failure === 'empty') {
                $translated[array_key_first($translated)] = '';
            } elseif ($failure === 'type') {
                $translated[array_key_first($translated)] = null;
            } elseif ($failure === 'extra') {
                $translated['s9999'] = 'unexpected';
            }
            $json = json_encode((object) $translated, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if ($failure === 'duplicate') {
                $key = array_key_first($translated);
                $json = substr($json, 0, -1) . ',"' . $key . '":"duplicate"}';
            }
            $usage = ['total_tokens' => 10, 'prompt_tokens' => 4, 'completion_tokens' => 6];
            if (str_starts_with(self::$scenario['model'] ?? '', 'google:')) {
                return self::response(json_encode([
                    'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => $json]]]]],
                    'usageMetadata' => ['totalTokenCount' => 10, 'promptTokenCount' => 4, 'candidatesTokenCount' => 6],
                ]));
            }
            if (isset($request['modelUri'])) {
                return self::response(json_encode([
                    'result' => ['alternatives' => [[
                        'status' => 'ALTERNATIVE_STATUS_FINAL',
                        'message' => ['text' => isset($request['jsonSchema']) || isset($request['jsonObject'])
                            ? $json : "‹‹TRANSLATION››\n" . $json . "\n‹‹END››"],
                    ]]],
                ]));
            }
            if (isset($request['input'])) {
                return self::response(json_encode([
                    'status' => 'completed', 'output' => [['type' => 'message', 'content' => [[
                        'type' => 'output_text', 'text' => isset($request['text']['format'])
                            ? $json : "‹‹TRANSLATION››\n" . $json . "\n‹‹END››",
                    ]]]], 'usage' => $usage,
                ]));
            }
            return self::response(json_encode([
                'choices' => [['finish_reason' => 'stop', 'message' => [
                    'content' => "‹‹TRANSLATION››\n" . $json . "\n‹‹END››",
                ]]], 'usage' => $usage,
            ]));
        }

        private static function translate(array $packet): array
        {
            return array_map(static fn (string $text): string => 'T:' . $text, $packet);
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
            modelKey: $scenario['model'] ?? 'openai:gpt-5-mini',
            format: $scenario['format'] ?? 'html',
            text: $scenario['input'] ?? '<p>One</p><p>Two</p><p>Three</p>',
            htmlTransport: $scenario['htmlTransport']
                ?? (($scenario['format'] ?? 'html') === 'text' ? 'document' : 'segments'),
            htmlJsonPointers: $scenario['htmlJsonPointers'] ?? [],
            htmlSegmentBatchSize: ($scenario['format'] ?? 'html') === 'text' ? null
                : (array_key_exists('batchSize', $scenario) ? $scenario['batchSize'] : 1),
            textTransport: ($scenario['format'] ?? 'html') === 'text' ? 'segments' : 'document',
            textSegmentBatchSize: ($scenario['format'] ?? 'html') === 'text' ? ($scenario['batchSize'] ?? null) : null,
            segmentBatchMaxBytes: $scenario['maxBytes'] ?? null,
            promptKey: $scenario['promptKey'] ?? 'translator',
            validate: $scenario['validate'] ?? true,
            dryRun: $scenario['dryRun'] ?? false,
            variables: $scenario['variables'] ?? [],
            context: $scenario['context'] ?? null,
        );
        $output = ['type' => 'success', 'result' => $result];
    } catch (\Throwable $exception) {
        $output = ['type' => $exception::class, 'message' => $exception->getMessage()];
    }
    $output['bodies'] = \Bblslug\HttpClient::$bodies;
    $output['packets'] = \Bblslug\HttpClient::$packets;
    $output['networkCalls'] = count(\Bblslug\HttpClient::$requests);
    echo json_encode($output, JSON_THROW_ON_ERROR);
}
