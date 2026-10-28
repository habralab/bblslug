<?php

declare(strict_types=1);

namespace Bblslug {
    class HttpClient
    {
        public static int $calls = 0;
        public static int $nativeSchemaPackets = 0;
        public static ?\HashContext $rawHash = null;

        /** @return array{status:int,body:string,headers:array<mixed>,debugRequest:string,debugResponse:string} */
        public static function request(...$arguments): array
        {
            $request = json_decode($arguments['body'], true, 512, JSON_THROW_ON_ERROR);
            $content = $request['messages'][1]['content'] ?? $request['input'][1]['content'];
            $payload = trim((string) preg_replace('/^‹‹TRANSLATION››\s*|\s*‹‹END››$/u', '', $content));
            $packet = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            if (strlen($payload) > 4096 || count($packet) > 64) {
                throw new \RuntimeException('Packet exceeds configured limits');
            }
            if (isset($request['text']['format']['schema'])) {
                $schema = $request['text']['format']['schema'];
                $ids = array_keys($packet);
                $properties = array_keys($schema['properties']);
                $required = $schema['required'];
                sort($ids);
                sort($properties);
                sort($required);
                if ($ids !== $properties || $ids !== $required) {
                    throw new \RuntimeException('Native schema differs from packet');
                }
                ++self::$nativeSchemaPackets;
            }
            ++self::$calls;
            $body = json_encode(isset($request['input']) ? [
                'status' => 'completed', 'output' => [['type' => 'message', 'content' => [[
                    'type' => 'output_text', 'text' => $payload,
                ]]]], 'usage' => ['input_tokens' => 4, 'output_tokens' => 6, 'total_tokens' => 10],
            ] : [
                'choices' => [['finish_reason' => 'stop', 'message' => [
                    'content' => "‹‹TRANSLATION››\n" . $payload . "\n‹‹END››",
                ]]],
                'usage' => ['prompt_tokens' => 4, 'completion_tokens' => 6, 'total_tokens' => 10],
            ], JSON_THROW_ON_ERROR);
            self::$rawHash ??= hash_init('sha256');
            hash_update(self::$rawHash, $body . "\0");
            return ['status' => 200, 'body' => $body, 'headers' => [], 'debugRequest' => '', 'debugResponse' => ''];
        }
    }
}

namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    $profile = $argv[1] ?? '';
    if (!in_array($profile, ['native-html', 'markers-html', 'text'], true)) {
        throw new \InvalidArgumentException('Unknown profile');
    }
    $html = $profile !== 'text';
    $source = $html ? str_repeat('<p>Hello</p>', intdiv(1048576, 12)) : str_repeat("Hi\n", intdiv(131072, 3));
    $result = \Bblslug\Bblslug::translate(
        modelKey: $profile === 'native-html' ? 'xai:grok-4.7' : 'openai:gpt-5-mini',
        apiKey: 'fixture-key',
        text: $source,
        format: $html ? 'html' : 'text',
        filters: [],
        validate: true,
        htmlTransport: $html ? 'segments' : 'document',
        htmlSegmentBatchSize: $html ? 64 : null,
        textTransport: $html ? 'document' : 'segments',
        textSegmentBatchSize: $html ? null : 64,
        segmentBatchMaxBytes: 4096,
        variables: ['json_transport' => $profile === 'native-html' ? 'native' : 'markers']
    );
    $calls = \Bblslug\HttpClient::$calls;
    $returnedHash = hash_init('sha256');
    foreach (json_decode($result['rawResponseBody'], true, 512, JSON_THROW_ON_ERROR) as $body) {
        hash_update($returnedHash, $body . "\0");
    }
    $actualRawHash = hash_final($returnedHash);
    $expectedRawHash = hash_final(\Bblslug\HttpClient::$rawHash);
    if (
        $result['result'] !== $source || $calls <= 100 || count($result['batches']) !== $calls
        || $result['consumed']['tokens']['total'] !== $calls * 10
        || $actualRawHash !== $expectedRawHash
        || ($profile === 'native-html' && \Bblslug\HttpClient::$nativeSchemaPackets !== $calls)
        || ($profile !== 'native-html' && \Bblslug\HttpClient::$nativeSchemaPackets !== 0)
    ) {
        throw new \RuntimeException('Translation result, usage, schema or raw responses differ: ' . json_encode([
            $calls, \Bblslug\HttpClient::$nativeSchemaPackets, count($result['batches']),
        ]));
    }
    echo json_encode([
        'bytes' => strlen($source),
        'calls' => $calls,
        'peak' => memory_get_peak_usage(true),
        'rawHash' => $actualRawHash,
        'nativeSchemaPackets' => \Bblslug\HttpClient::$nativeSchemaPackets,
    ], JSON_THROW_ON_ERROR);
}
