<?php

declare(strict_types=1);

namespace Bblslug {
    /** Exercise the full API without credentials or provider HTTP. */
    class HttpClient
    {
        public static int $calls = 0;
        public static bool $reversed = false;
        public static ?\HashContext $rawHash = null;
        public static ?\HashContext $packetHash = null;

        /** @return array{status:int,body:string,headers:array<mixed>,debugRequest:string,debugResponse:string} */
        public static function request(...$arguments): array
        {
            $request = json_decode($arguments['body'], true, 512, JSON_THROW_ON_ERROR);
            $payload = trim((string) preg_replace(
                '/^‹‹TRANSLATION››\s*|\s*‹‹END››$/u',
                '',
                $request['messages'][1]['content']
            ));
            $values = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            if (strlen($payload) > 4096 || count($values) > 64) {
                throw new \RuntimeException('Restoration fixture exceeded packet limits');
            }
            self::$packetHash ??= hash_init('sha256');
            hash_update(self::$packetHash, $payload . "\0");
            if (self::$reversed) {
                $values = array_reverse(array_map(strtoupper(...), $values), true);
            }
            ++self::$calls;
            $body = json_encode([
                'choices' => [['finish_reason' => 'stop', 'message' => [
                    'content' => "‹‹TRANSLATION››\n" . json_encode((object) $values, JSON_THROW_ON_ERROR) . "\n‹‹END››",
                ]]],
                'usage' => [
                    'total_tokens' => 10, 'prompt_tokens' => 4, 'completion_tokens' => 6,
                    'completion_tokens_details' => ['reasoning_tokens' => 2],
                ],
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
    if (!in_array($profile, ['identity', 'translated-reversed'], true)) {
        throw new \InvalidArgumentException('Unknown restoration memory profile');
    }
    \Bblslug\HttpClient::$reversed = $profile === 'translated-reversed';
    $source = str_repeat("Hi\n", intdiv(1024 * 1024, 3));
    $result = \Bblslug\Bblslug::translate(
        modelKey: 'openai:gpt-5-mini',
        apiKey: 'fixture-key',
        text: $source,
        format: 'text',
        filters: [],
        validate: true,
        textTransport: 'segments',
        textSegmentBatchSize: 64,
        segmentBatchMaxBytes: 4096,
        variables: ['json_transport' => 'markers']
    );
    $calls = \Bblslug\HttpClient::$calls;
    $expected = \Bblslug\HttpClient::$reversed ? strtoupper($source) : $source;
    if (
        $result['result'] !== $expected || $result['original'] !== $source || $result['prepared'] !== $source
        || $calls !== intdiv(substr_count($source, "\n") + 63, 64)
        || count($result['batches']) !== $calls || $result['httpStatus'] !== 200
        || $result['consumed']['tokens']['total'] !== $calls * 10
        || $result['consumed']['tokens']['breakdown']['prompt'] !== $calls * 4
        || $result['consumed']['tokens']['breakdown']['completion'] !== $calls * 6
        || $result['consumed']['tokens']['breakdown']['reasoning'] !== $calls * 2
    ) {
        throw new \RuntimeException('Restoration changed source, translations, batching or usage');
    }
    $responses = json_decode($result['rawResponseBody'], true, 512, JSON_THROW_ON_ERROR);
    $rawHash = hash_init('sha256');
    foreach ($responses as $response) {
        hash_update($rawHash, $response . "\0");
    }
    $digest = hash_final($rawHash);
    if (count($responses) !== $calls || $digest !== hash_final(\Bblslug\HttpClient::$rawHash)) {
        throw new \RuntimeException('Restoration changed the raw response sequence');
    }
    echo json_encode([
        'bytes' => strlen($source), 'calls' => $calls, 'peak' => memory_get_peak_usage(true),
        'resultHash' => hash('sha256', $result['result']), 'rawHash' => $digest,
        'packetHash' => hash_final(\Bblslug\HttpClient::$packetHash),
    ], JSON_THROW_ON_ERROR);
}
