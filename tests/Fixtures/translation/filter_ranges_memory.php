<?php

declare(strict_types=1);

namespace Bblslug {
    class HttpClient
    {
        public static int $calls = 0;

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
            if (strlen($payload) > 4096 || count($values) > 64 || array_unique(array_values($values)) !== ['Hello']) {
                throw new \RuntimeException('Protected fragments reached the provider or exceeded packet limits');
            }
            ++self::$calls;
            return [
                'status' => 200,
                'body' => json_encode([
                    'choices' => [['finish_reason' => 'stop', 'message' => [
                        'content' => "‹‹TRANSLATION››\n" . $payload . "\n‹‹END››",
                    ]]], 'usage' => ['total_tokens' => 10],
                ], JSON_THROW_ON_ERROR),
                'headers' => [], 'debugRequest' => '', 'debugResponse' => '',
            ];
        }
    }
}

namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    $profile = $argv[1] ?? '';
    if (!in_array($profile, ['url', 'html', 'nested-url', 'nested-html', 'api-url', 'api-html'], true)) {
        throw new \InvalidArgumentException('Unknown filter range memory profile');
    }
    $url = str_ends_with($profile, 'url');
    $api = str_starts_with($profile, 'api-');
    $depth = str_starts_with($profile, 'nested-') ? 2 : 0;
    $name = $url ? 'url' : 'html_b';
    $capture = $url ? 'https://x.test/a' : '<b>x</b>';
    $unit = $api ? ($url ? "Hello https://x.test/a\n" : "<b>x</b> Hello\n") : $capture . ' ';
    $bytes = ($api ? 1 : 4) * 1024 * 1024;
    $copies = intdiv($bytes, strlen($unit));
    $source = ($api ? '' : "é @@9@@ ") . str_repeat($unit, $copies);
    for ($level = 0; $level < $depth; ++$level) {
        $source = json_encode($source, JSON_THROW_ON_ERROR);
        $capture = substr(json_encode($capture, JSON_THROW_ON_ERROR), 1, -1);
    }
    $debugHash = '';
    $preparedHash = '';
    if ($api) {
        $result = \Bblslug\Bblslug::translate(
            modelKey: 'openai:gpt-5-mini',
            apiKey: 'fixture-key',
            text: $source,
            format: $url ? 'text' : 'html',
            filters: [$name],
            validate: true,
            textTransport: $url ? 'segments' : 'document',
            htmlTransport: $url ? 'document' : 'segments',
            textSegmentBatchSize: $url ? 64 : null,
            htmlSegmentBatchSize: $url ? null : 64,
            segmentBatchMaxBytes: 4096,
            variables: ['json_transport' => 'markers']
        );
        if (
            $result['result'] !== $source || \Bblslug\HttpClient::$calls === 0
            || $result['consumed']['tokens']['total'] !== \Bblslug\HttpClient::$calls * 10
            || count($result['batches']) !== \Bblslug\HttpClient::$calls
            || $result['filterStats'] !== [['filter' => $name, 'count' => $copies]]
        ) {
            throw new \RuntimeException('Filtered API result, usage, batches or statistics changed');
        }
        $preparedHash = hash('sha256', $result['prepared']);
    } else {
        $filter = $url ? new \Bblslug\Filters\UrlFilter() : new \Bblslug\Filters\HtmlTagFilter('b');
        $counter = new \Bblslug\Filters\PlaceholderCounter();
        $prepared = $filter->apply($source, $counter);
        if (
            $counter->current() !== $copies + 1 || $filter->getStats() !== ['filter' => $name, 'count' => $copies]
            || $filter->restore($prepared) !== $source
        ) {
            throw new \RuntimeException('Filter source, capture count, shielding or restoration changed');
        }
        $decoded = $prepared;
        for ($level = 0; $level < $depth; ++$level) {
            $decoded = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        }
        unset($decoded);
        $map = $filter->debugDump();
        $digest = hash_init('sha256');
        $index = 1;
        foreach ($map as $marker => $value) {
            if ($marker !== '@@' . $index . '@@' || $value !== $capture) {
                throw new \RuntimeException('Diagnostic map changed marker order or raw source bytes');
            }
            hash_update($digest, $marker . "\0" . $value . "\0");
            ++$index;
        }
        if (count($map) !== $copies || $filter->restore($prepared) !== $source) {
            throw new \RuntimeException('Diagnostic map access changed filter restoration');
        }
        $debugHash = hash_final($digest);
        $preparedHash = hash('sha256', $prepared);
    }
    echo json_encode([
        'bytes' => strlen($source), 'count' => $copies, 'calls' => \Bblslug\HttpClient::$calls,
        'peak' => memory_get_peak_usage(true), 'resultHash' => hash('sha256', $source),
        'preparedHash' => $preparedHash, 'debugHash' => $debugHash,
    ], JSON_THROW_ON_ERROR);
}
